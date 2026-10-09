<?php

namespace App\PublicSite;

use App\PublicResults\CompetitionRules;
use App\PublicSite\Dto\CalendarEntry;
use App\PublicSite\Dto\ClubSheet;
use App\PublicSite\Dto\ClubSummary;
use App\PublicSite\Dto\Honour;
use App\PublicSite\Dto\PodiumTeam;
use App\PublicSite\Dto\RosterPlayer;
use App\PublicSite\Dto\SearchCompetition;
use App\PublicSite\Dto\SearchEvent;
use App\PublicSite\Dto\TeamSheet;
use App\PublicSite\Dto\TeamSummary;
use App\PublicSite\Ics\IcsCalendar;
use App\PublicSite\Ics\IcsEvent;
use Psr\Clock\ClockInterface;

/**
 * Données des endpoints publics transverses du site public (API_PUBLIC_TRANSVERSE.md). Chaque méthode
 * renvoie `null` quand la ressource est inconnue ou non publiée (le contrôleur répond alors 404).
 */
final class PublicSiteService
{
    public const TEAM_SEARCH_LIMIT = 20;

    public const GLOBAL_SEARCH_LIMIT = 8;

    public function __construct(
        private readonly PublicSiteRepository $repository,
        private readonly MediaFiles $media,
        private readonly ClockInterface $clock,
        private readonly string $publicSiteUrl,
    ) {
    }

    // ------------------------------------------------------------------ calendrier et ICS

    /** @return list<CalendarEntry> */
    public function calendar(string $start, string $end, ?string $level, ?string $group): array
    {
        $rows = PublicSiteRules::mergeGamedays($this->repository->findCalendarGamedays($start, $end, $level, $group));

        return array_map(static fn (array $row): CalendarEntry => new CalendarEntry(
            id: (int) $row['Id'],
            competition: [
                'season' => $row['Code_saison'],
                'code' => $row['Code'],
                'display_title' => self::displayTitle($row),
                'type' => (string) $row['Code_typeclt'],
                'level' => $row['Code_niveau'],
                'group' => $row['Code_ref'],
            ],
            name: $row['Nom'],
            place: $row['Lieu'],
            department: $row['Departement'],
            start: $row['Date_debut'],
            end: $row['Date_fin'],
            event: $row['event_id'] === null ? null : ['id' => (int) $row['event_id'], 'libelle' => $row['event_libelle']],
        ), $rows);
    }

    /** Abonnement iCalendar d'une compétition (publiée : vérifié par l'appelant). */
    public function competitionIcs(string $season, string $code, string $competitionTitle): string
    {
        $events = array_map(fn (array $row): IcsEvent => $this->icsEvent($row), $this->repository->findIcsGamedays($season, $code));

        return IcsCalendar::render(sprintf('%s (%s)', $competitionTitle, $season), $events, $this->clock->now());
    }

    public function gamedayIcs(int $id): ?string
    {
        $row = $this->repository->findIcsGameday($id);
        if ($row === null) {
            return null;
        }
        $event = $this->icsEvent($row);

        return IcsCalendar::render($event->summary, [$event], $this->clock->now());
    }

    // ------------------------------------------------------------------ historique

    /** @return array{sections: list<array<string, mixed>>} */
    public function historyGroups(): array
    {
        return ['sections' => GroupSections::organize($this->repository->findHistoryGroups())];
    }

    /** @return array<string, mixed>|null */
    public function history(string $groupCode): ?array
    {
        $group = $this->repository->findGroup($groupCode);
        $competitions = $group === null ? [] : $this->repository->findHistoryCompetitions($groupCode);
        if ($group === null || $competitions === []) {
            return null;
        }

        $teams = [];
        foreach ($this->repository->findHistoryTeams($groupCode) as $team) {
            $teams[$team['Code_saison'] . '|' . $team['Code_compet']][] = $team;
        }

        $seasons = [];
        foreach ($competitions as $row) {
            $type = (string) $row['Code_typeclt'];
            $final = CompetitionRules::isFinalRound($row['Code_tour'] === null ? null : (int) $row['Code_tour']);
            $seasons[$row['Code_saison']][] = [
                'code' => $row['Code'],
                'display_title' => self::displayTitle($row),
                'soustitre2' => $row['Soustitre2'],
                'type' => $type,
                'podium' => $this->podium($teams[$row['Code_saison'] . '|' . $row['Code']] ?? [], $type, $row['Statut'], $final),
            ];
        }

        return [
            'group' => $group,
            'seasons' => array_map(
                static fn (string|int $season, array $list): array => ['season' => (string) $season, 'competitions' => $list],
                array_keys($seasons),
                array_values($seasons),
            ),
        ];
    }

    // ------------------------------------------------------------------ équipes

    /** @return list<TeamSummary> */
    public function searchTeams(string $term, int $limit = self::TEAM_SEARCH_LIMIT): array
    {
        return array_map(self::teamSummary(...), $this->repository->searchTeams($term, $limit));
    }

    public function team(int $number): ?TeamSheet
    {
        $row = $this->repository->findTeam($number);
        if ($row === null) {
            return null;
        }

        $honours = [];
        foreach ($this->repository->findTeamHonours($number) as $honour) {
            $type = (string) $honour['Code_typeclt'];
            $rank = CompetitionRules::rank($type, (int) $honour['Clt_publi'], (int) $honour['CltNiveau_publi']);
            if ($rank === 0) {
                continue;
            }
            $final = CompetitionRules::isFinalRound($honour['Code_tour'] === null ? null : (int) $honour['Code_tour']);
            $honours[] = new Honour(
                season: $honour['Code_saison'],
                code: $honour['Code'],
                displayTitle: self::displayTitle($honour),
                group: $honour['Code_ref'],
                rank: $rank,
                medal: CompetitionRules::medal($honour['Statut'], $final, $rank),
            );
        }

        $seasons = [];
        foreach ($this->repository->findTeamCompetitions($number) as $competition) {
            $seasons[$competition['Code_saison']][] = ['code' => $competition['Code'], 'display_title' => self::displayTitle($competition)];
        }

        $year = (int) $this->clock->now()->format('Y');
        $clubCode = PublicSiteRules::nullIfEmpty($row['Code_club']);

        return new TeamSheet(
            team: self::teamSummary($row),
            logo: $clubCode === null ? null : $this->media->clubLogo($clubCode),
            colors: $this->media->teamColors($number, $year),
            photo: $this->media->teamPhoto($number, $year),
            honours: $honours,
            seasons: array_map(
                static fn (string|int $season, array $list): array => ['season' => (string) $season, 'competitions' => $list],
                array_keys($seasons),
                array_values($seasons),
            ),
        );
    }

    /** @return array{players: list<RosterPlayer>}|null */
    public function roster(int $number, string $season, string $code): ?array
    {
        if (!$this->repository->isTeamEngaged($number, $season, $code)) {
            return null;
        }

        return ['players' => array_map(static fn (array $row): RosterPlayer => new RosterPlayer(
            firstName: $row['Prenom'],
            lastName: $row['Nom'],
            number: $row['Numero'] === null ? null : (int) $row['Numero'],
            category: PublicSiteRules::nullIfEmpty($row['Categ']),
            role: PublicSiteRules::role($row['Capitaine']),
            goals: (int) $row['goals'],
            green: (int) $row['green'],
            yellow: (int) $row['yellow'],
            red: (int) $row['red'],
            redFinal: (int) $row['red_final'],
        ), $this->repository->findRoster($number, $season, $code))];
    }

    // ------------------------------------------------------------------ clubs

    /** @return list<ClubSummary> */
    public function clubs(?string $term, ?int $limit = null): array
    {
        return array_map(fn (array $row): ClubSummary => $this->clubSummary($row), $this->repository->findClubs($term, $limit));
    }

    public function club(string $code): ?ClubSheet
    {
        $row = $this->repository->findClub($code);
        if ($row === null) {
            return null;
        }

        return new ClubSheet(
            club: $this->clubSummary($row),
            region: ['code' => PublicSiteRules::nullIfEmpty($row['Code_comite_reg']), 'label' => PublicSiteRules::nullIfEmpty($row['region_label'])],
            www: PublicSiteRules::nullIfEmpty($row['www']),
            email: PublicSiteRules::nullIfEmpty($row['email']),
            postal: PublicSiteRules::nullIfEmpty($row['Postal']),
            teams: array_map(
                static fn (array $team): array => ['number' => (int) $team['Numero'], 'label' => $team['Libelle']],
                $this->repository->findClubTeams($code),
            ),
        );
    }

    // ------------------------------------------------------------------ recherche globale

    /** @return array{competitions: list<SearchCompetition>, events: list<SearchEvent>, teams: list<TeamSummary>, clubs: list<ClubSummary>} */
    public function search(string $term): array
    {
        $limit = self::GLOBAL_SEARCH_LIMIT;

        return [
            'competitions' => array_map(static fn (array $row): SearchCompetition => new SearchCompetition(
                season: $row['Code_saison'],
                code: $row['Code'],
                displayTitle: self::displayTitle($row),
                soustitre2: $row['Soustitre2'],
                group: $row['Code_ref'],
            ), $this->repository->searchCompetitions($term, $this->repository->findActiveSeason(), $limit)),
            'events' => array_map(static fn (array $row): SearchEvent => new SearchEvent(
                id: (int) $row['Id'],
                libelle: (string) $row['Libelle'],
                place: $row['Lieu'],
                start: $row['Date_debut'],
                end: $row['Date_fin'],
            ), $this->repository->searchEvents($term, $limit)),
            'teams' => $this->searchTeams($term, $limit),
            'clubs' => $this->clubs($term, $limit),
        ];
    }

    // ------------------------------------------------------------------ privé

    /**
     * Classées (rang > 0) au rang propre au type, par rang puis différence de buts, avec médailles.
     *
     * @param list<array<string, mixed>> $teams
     *
     * @return list<PodiumTeam>
     */
    private function podium(array $teams, string $type, string $status, bool $final): array
    {
        $ranked = [];
        foreach ($teams as $team) {
            $rank = CompetitionRules::rank($type, (int) $team['Clt_publi'], (int) $team['CltNiveau_publi']);
            if ($rank > 0) {
                $ranked[] = ['rank' => $rank, 'diff' => (int) $team['Diff_publi'], 'team' => $team];
            }
        }
        usort($ranked, static fn (array $a, array $b): int => [$a['rank'], $b['diff'], $a['team']['Libelle']] <=> [$b['rank'], $a['diff'], $b['team']['Libelle']]);

        return array_map(static fn (array $entry): PodiumTeam => new PodiumTeam(
            rank: $entry['rank'],
            number: $entry['team']['Numero'] === null ? null : (int) $entry['team']['Numero'],
            label: $entry['team']['Libelle'],
            logo: $entry['team']['logo'],
            medal: CompetitionRules::medal($status, $final, $entry['rank']),
        ), $ranked);
    }

    /** @param array<string, mixed> $row */
    private function icsEvent(array $row): IcsEvent
    {
        $title = self::displayTitle($row);
        $name = PublicSiteRules::nullIfEmpty($row['Nom']);
        $place = PublicSiteRules::nullIfEmpty($row['Lieu']);
        $department = PublicSiteRules::nullIfEmpty($row['Departement']);
        $url = sprintf('%s/competitions/%s/%s', rtrim($this->publicSiteUrl, '/'), rawurlencode($row['Code_saison']), rawurlencode($row['Code']));
        if ($row['Code_typeclt'] === 'CHPT') {
            $url .= '/games?gameday=' . (int) $row['Id'];
        }

        return new IcsEvent(
            uid: IcsEvent::gamedayUid((int) $row['Id']),
            start: new \DateTimeImmutable($row['Date_debut']),
            end: new \DateTimeImmutable($row['Date_fin']),
            summary: $name === null || $name === $title ? $title : $title . ' — ' . $name,
            location: $place === null ? null : ($department === null ? $place : sprintf('%s (%s)', $place, $department)),
            url: $url,
        );
    }

    /** @param array<string, mixed> $row */
    private static function teamSummary(array $row): TeamSummary
    {
        return new TeamSummary(
            number: (int) $row['Numero'],
            label: $row['Libelle'],
            clubCode: PublicSiteRules::nullIfEmpty($row['Code_club']),
            clubLabel: PublicSiteRules::nullIfEmpty($row['club_label']),
        );
    }

    /** @param array<string, mixed> $row */
    private function clubSummary(array $row): ClubSummary
    {
        return new ClubSummary(
            code: $row['Code'],
            label: $row['Libelle'],
            departmentCode: PublicSiteRules::nullIfEmpty($row['Code_comite_dep']),
            departmentLabel: PublicSiteRules::nullIfEmpty($row['department_label']),
            logo: $this->media->clubLogo($row['Code']),
            position: PublicSiteRules::position($row['Coord']),
        );
    }

    /** @param array<string, mixed> $row ligne avec Titre_actif, Libelle, Soustitre */
    private static function displayTitle(array $row): string
    {
        return CompetitionRules::displayTitle((string) $row['Titre_actif'], $row['Libelle'], $row['Soustitre']);
    }
}
