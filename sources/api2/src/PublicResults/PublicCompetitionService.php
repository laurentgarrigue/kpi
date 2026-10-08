<?php

namespace App\PublicResults;

use App\PublicResults\Dto\CompetitionHeader;
use App\PublicResults\Dto\LinkedEvent;
use App\PublicResults\Dto\RankingRow;
use App\PublicResults\Dto\TeamRef;
use App\PublicResults\Scope\CompetitionScope;
use App\PublicResults\Scope\GroupScope;
use App\PublicResults\Scope\ResultsScope;

/**
 * Données publiques des compétitions pour le site public (API_PUBLIC_RESULTS.md § 4 et § 5). Chaque méthode
 * renvoie `null` quand la ressource est inconnue ou non publiée (le contrôleur répond alors 404).
 */
final class PublicCompetitionService
{
    /** Statuts pour lesquels les équipes engagées sont publiées (kpdetails.php). */
    private const TEAMS_VISIBLE_STATUSES = ['ON', 'END'];

    private const SHARE_PRECISION = 3;

    public function __construct(
        private readonly PublicCompetitionRepository $repository,
        private readonly string $legacyDocumentRoot,
    ) {
    }

    /** @return array{active: ?string, seasons: list<string>} */
    public function seasons(): array
    {
        return ['active' => $this->repository->findActiveSeason(), 'seasons' => $this->repository->findSeasons()];
    }

    /** @return array{events: list<LinkedEvent>, competitions: list<array<string, mixed>>}|null */
    public function groupCompetitions(string $season, string $groupCode): ?array
    {
        $rows = $this->repository->findGroupCompetitions($season, $groupCode);
        if ($rows === []) {
            return null;
        }

        $competitions = array_map(function (array $row): array {
            $header = $this->header($row);

            return [...$header->jsonSerialize(), 'ranking' => $this->rankingRows($header, compact: true)];
        }, $rows);

        return ['events' => $this->linkedEvents(new GroupScope($season, $groupCode)), 'competitions' => $competitions];
    }

    /** @return array<string, mixed>|null */
    public function competition(string $season, string $code): ?array
    {
        $header = $this->findHeader($season, $code);
        if ($header === null) {
            return null;
        }

        $siblings = array_map(
            fn (array $row) => $this->header($row)->summary(),
            $this->repository->findGroupCompetitions($season, $header->group['code'], navigationOrder: true),
        );

        return [
            ...$header->jsonSerialize(),
            'siblings' => $siblings,
            'events' => $this->linkedEvents(new CompetitionScope($season, $code)),
        ];
    }

    public function findHeader(string $season, string $code): ?CompetitionHeader
    {
        $row = $this->repository->findCompetition($season, $code);

        return $row === null ? null : $this->header($row);
    }

    /** @return array{status: string, type: string, qualified: int, eliminated: int, rows: list<RankingRow>}|null */
    public function ranking(string $season, string $code): ?array
    {
        $header = $this->findHeader($season, $code);
        if ($header === null) {
            return null;
        }

        $teams = $this->repository->findRankedTeams($season, $code, $header->type);
        // Règle legacy : une équipe classée au rang 0 rend les marques qualifiés / éliminés trompeuses.
        $hasUnrankedTeam = array_filter($teams, fn (array $team): bool => $this->teamRank($header, $team) === 0) !== [];

        return [
            'status' => $header->status,
            'type' => $header->type,
            'qualified' => $hasUnrankedTeam ? 0 : $header->qualified,
            'eliminated' => $hasUnrankedTeam ? 0 : $header->eliminated,
            'rows' => $this->rankingRows($header, compact: false, teams: $teams),
        ];
    }

    /** @return array<string, mixed>|null */
    public function info(string $season, string $code): ?array
    {
        $header = $this->findHeader($season, $code);
        if ($header === null) {
            return null;
        }

        $gamedays = array_map(static fn (array $row): array => [
            'id' => (int) $row['Id'],
            'name' => $row['Nom'],
            'phase' => $row['Phase'],
            'start' => $row['Date_debut'],
            'end' => $row['Date_fin'],
            'place' => $row['Lieu'],
            'department' => $row['Departement'],
            'organizer' => $row['Organisateur'],
            'officials' => [
                'rc' => CompetitionRules::personName($row['Responsable_insc']),
                'r1' => CompetitionRules::personName($row['Responsable_R1']),
                'delegate' => CompetitionRules::personName($row['Delegue']),
                'chief_referee' => CompetitionRules::personName($row['ChefArbitre']),
            ],
        ], $this->repository->findGamedays($season, $code));

        return [
            'gamedays' => $gamedays,
            'teams_by_pool' => in_array($header->status, self::TEAMS_VISIBLE_STATUSES, true)
                ? $this->teamsByPool($season, $code)
                : [],
            'schema' => $this->schemaPath($season, $code),
        ];
    }

    /** @return array{event: array<string, mixed>, competitions: list<array<string, mixed>>}|null */
    public function eventCompetitions(int $eventId): ?array
    {
        $event = $this->repository->findEvent($eventId);
        if ($event === null) {
            return null;
        }

        return [
            'event' => [
                'id' => (int) $event['Id'],
                'libelle' => $event['Libelle'],
                'place' => $event['Lieu'],
                'logo' => $event['logo'],
                'start' => $event['Date_debut'],
                'end' => $event['Date_fin'],
            ],
            'competitions' => array_map(
                fn (array $row): array => $this->header($row)->summary()->withSeason(),
                $this->repository->findEventCompetitions($eventId),
            ),
        ];
    }

    /** @param array<string, mixed> $row ligne de PublicCompetitionRepository (HEADER_SELECT) */
    private function header(array $row): CompetitionHeader
    {
        $round = $row['Code_tour'] === null ? null : (int) $row['Code_tour'];

        return new CompetitionHeader(
            code: $row['Code'],
            season: $row['Code_saison'],
            group: ['code' => $row['Code_ref'], 'libelle' => $row['group_libelle'], 'libelle_en' => $row['group_libelle_en']],
            libelle: $row['Libelle'],
            soustitre: $row['Soustitre'],
            soustitre2: $row['Soustitre2'],
            displayTitle: CompetitionRules::displayTitle($row['Titre_actif'], $row['Libelle'], $row['Soustitre']),
            type: (string) $row['Code_typeclt'],
            status: $row['Statut'],
            level: $row['Code_niveau'],
            banner: CompetitionRules::visual($row['Bandeau_actif'], $row['BandeauLink']),
            logo: CompetitionRules::visual($row['Logo_actif'], $row['LogoLink']),
            web: $row['Web'] === '' ? null : $row['Web'],
            qualified: (int) $row['Qualifies'],
            eliminated: (int) $row['Elimines'],
            hasGames: (bool) $row['has_games'],
            round: $round,
            final: CompetitionRules::isFinalRound($round),
        );
    }

    /**
     * @param list<array<string, mixed>>|null $teams équipes déjà lues, sinon lues ici
     *
     * @return list<RankingRow>
     */
    private function rankingRows(CompetitionHeader $header, bool $compact, ?array $teams = null): array
    {
        if (!CompetitionRules::isRankingPublished($header->type, $header->status)) {
            return [];
        }

        $rows = [];
        foreach ($teams ?? $this->repository->findRankedTeams($header->season, $header->code, $header->type) as $team) {
            $rank = $this->teamRank($header, $team);
            if ($rank === 0) {
                continue;
            }
            $withDetails = !$compact && $header->type !== 'MULTI';
            $rows[] = new RankingRow(
                rank: $rank,
                team: new TeamRef((int) $team['Id'], $team['Numero'] === null ? null : (int) $team['Numero'], $team['Libelle'], $team['logo']),
                points: CompetitionRules::points((int) $team['Pts_publi']),
                played: (int) $team['J_publi'],
                medal: CompetitionRules::medal($header->status, $header->final, $rank),
                details: $withDetails ? [
                    'won' => (int) $team['G_publi'],
                    'drawn' => (int) $team['N_publi'],
                    'lost' => (int) $team['P_publi'],
                    'forfeits' => (int) $team['F_publi'],
                    'goals_for' => (int) $team['Plus_publi'],
                    'goals_against' => (int) $team['Moins_publi'],
                    'goal_diff' => (int) $team['Diff_publi'],
                ] : null,
            );
        }

        return $rows;
    }

    /** @param array<string, mixed> $team */
    private function teamRank(CompetitionHeader $header, array $team): int
    {
        return CompetitionRules::rank($header->type, (int) $team['Clt_publi'], (int) $team['CltNiveau_publi']);
    }

    /** @return list<LinkedEvent> par part décroissante, puis date de début */
    private function linkedEvents(ResultsScope $scope): array
    {
        $total = $this->repository->countGamedays($scope);
        if ($total === 0) {
            return [];
        }

        $events = array_map(static fn (array $row): LinkedEvent => new LinkedEvent(
            id: (int) $row['Id'],
            libelle: $row['Libelle'],
            place: $row['Lieu'],
            start: $row['Date_debut'],
            end: $row['Date_fin'],
            logo: $row['logo'],
            share: round((int) $row['gamedays'] / $total, self::SHARE_PRECISION),
        ), $this->repository->findLinkedEvents($scope));

        usort($events, static fn (LinkedEvent $a, LinkedEvent $b): int => [$b->share, $a->start] <=> [$a->share, $b->start]);

        return $events;
    }

    /** @return list<array{pool: string, teams: list<TeamRef>}> */
    private function teamsByPool(string $season, string $code): array
    {
        $pools = [];
        foreach ($this->repository->findTeamsByPool($season, $code) as $team) {
            $pools[$team['Poule']][] = new TeamRef(
                (int) $team['Id'],
                $team['Numero'] === null ? null : (int) $team['Numero'],
                $team['Libelle'],
                $team['logo'],
            );
        }

        return array_map(
            static fn (string|int $pool, array $teams): array => ['pool' => (string) $pool, 'teams' => $teams],
            array_keys($pools),
            array_values($pools),
        );
    }

    /** Schéma de la compétition sous /img/schemas/, s'il existe dans l'arborescence legacy. */
    private function schemaPath(string $season, string $code): ?string
    {
        $path = sprintf('schemas/schema_%s_%s.png', $season, $code);

        return is_file($this->legacyDocumentRoot . '/img/' . $path) ? $path : null;
    }
}
