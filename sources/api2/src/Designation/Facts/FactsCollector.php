<?php

namespace App\Designation\Facts;

use App\Competition\CompetitionLevel;
use App\Designation\Authority;
use App\Designation\DesignationRepository;
use App\Designation\EffectiveDesignations;
use App\Designation\GamedayScope;
use App\Designation\ScopeType;
use App\Report\Report;
use App\Report\ReportTypeCatalog;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Relevé des faits d'une saison pour les rétributions et pénalités (FEATURE_DESIGNATIONS.md D19, D22, D23 ; § 6) :
 * compétitions nationales seulement, quantités par club, sources tracées. Les bilans de journée pris en compte sont
 * les bilans soumis (présence effective des officiels, podium, finale, terrains, tenue, infractions).
 */
final class FactsCollector
{
    /** Officiels « fournis » par un club (annexe 8 § 1) ; l'arbitre non-joueur seulement s'il vient de l'organisateur. */
    private const PROVIDED_ROLES = ['DELEGUE_CNA', 'JUGE_ARBITRE', 'ARBITRE_NJ'];
    private const CARDS = ['J' => Fact::CARD_YELLOW, 'R' => Fact::CARD_RED, 'D' => Fact::CARD_BLACK];
    private const ABSENT = ['absent', 'replaced'];
    private const VALIDATED = 'O';

    public function __construct(
        private readonly Connection $connection,
        private readonly DesignationRepository $designations,
    ) {
    }

    /** @return array{facts: list<Fact>, clubLabels: array<string, string>} */
    public function collect(string $season): array
    {
        $gamedays = array_values(array_filter(
            $this->connection->fetchAllAssociative(
                "SELECT j.Id, j.Code_competition, j.Code_organisateur, j.Phase, j.Nom, j.Date_debut,
                        GROUP_CONCAT(DISTINCT ej.Id_evenement) AS events
                 FROM kp_journee j LEFT JOIN kp_evenement_journee ej ON ej.Id_journee = j.Id
                 WHERE j.Code_saison = ? GROUP BY j.Id ORDER BY j.Date_debut, j.Id",
                [$season],
            ),
            static fn (array $row): bool => CompetitionLevel::isNational($row['Code_competition']),
        ));
        $bilans = $this->bilans($season);
        $facts = [];
        array_push($facts, ...$this->hosting($gamedays, $bilans));
        array_push($facts, ...$this->managers($season));
        array_push($facts, ...$this->officials($season, $gamedays, $bilans));
        array_push($facts, ...$this->cards(array_map(static fn (array $g): int => (int) $g['Id'], $gamedays)));
        array_push($facts, ...$this->fromBilans($bilans));

        return ['facts' => $facts, 'clubLabels' => $this->clubLabels()];
    }

    /**
     * Journées accueillies et terrains par compétition ; finale et podium déclarés dans le bilan (§ 6).
     *
     * @param list<array<string, mixed>>                  $gamedays
     * @param list<array{report: BilanAnswers, gamedays: list<int>}> $bilans
     *
     * @return list<Fact>
     */
    private function hosting(array $gamedays, array $bilans): array
    {
        $facts = [];
        $pitches = $this->pitches(array_map(static fn (array $g): int => (int) $g['Id'], $gamedays));
        $podiumClubs = [];
        foreach ($gamedays as $gameday) {
            $club = $gameday['Code_organisateur'] ?: null;
            if ($club === null) {
                continue;
            }
            $id = (int) $gameday['Id'];
            $bilan = self::bilanOf($bilans, $id)?->answers ?? [];
            $label = sprintf('%s · %s (%s)', $gameday['Code_competition'], $gameday['Phase'] ?: $gameday['Nom'], $gameday['Date_debut']);
            $final = ($bilan['final'] ?? false) === true;
            $count = $pitches[$id] ?? 0;
            if ($count === 0 && is_int($bilan['pitches_used'] ?? null)) {
                $count = $bilan['pitches_used'];
            }
            $facts[] = new Fact($club, $final ? Fact::FINAL_HOSTED : Fact::GAMEDAY_HOSTED, $gameday['Code_competition'], 1, $label, 'JOURNEE', (string) $id);
            $facts[] = new Fact($club, Fact::GAMEDAY_PITCHES, $gameday['Code_competition'], $count, $label, 'JOURNEE', (string) $id);
            $bilanReport = self::bilanOf($bilans, $id);
            if ($bilanReport !== null && ($bilanReport->answers['podium'] ?? false) === true) {
                $podiumClubs["$club|{$bilanReport->id}"] = new Fact($club, Fact::PODIUM, '', 1, $label, 'REPORT', (string) $bilanReport->id);
            }
        }

        return [...$facts, ...array_values($podiumClubs)];
    }

    /**
     * RC fournis par club, par compétition nationale (club figé dans la Désignation, D22).
     *
     * @return list<Fact>
     */
    private function managers(string $season): array
    {
        $facts = [];
        foreach ($this->designations->ofSeason($season, 'RC') as $rc) {
            if ($rc->scopeType === ScopeType::Competition && CompetitionLevel::isNational($rc->scopeId)) {
                $facts[] = new Fact($rc->club, Fact::RC_PROVIDED, $rc->scopeId, 1, sprintf('Licence %d', $rc->matric), 'DESIGNATION', (string) $rc->id);
            }
        }

        return $facts;
    }

    /**
     * Délégués CNA, juges arbitres et arbitres non-joueurs (désignés par l'organisateur, D23) fournis, par journée
     * nationale (REL-02, REL-03) ; un officiel déclaré absent ou remplacé dans le bilan soumis ne compte pas (BIL-06).
     *
     * @param list<array<string, mixed>>                  $gamedays
     * @param list<array{report: BilanAnswers, gamedays: list<int>}> $bilans
     *
     * @return list<Fact>
     */
    private function officials(string $season, array $gamedays, array $bilans): array
    {
        $designations = $this->designations->ofSeason($season);
        $facts = [];
        foreach ($gamedays as $gameday) {
            $id = (int) $gameday['Id'];
            $events = $gameday['events'] === null ? [] : array_map('intval', explode(',', $gameday['events']));
            $effective = EffectiveDesignations::of(new GamedayScope($id, $season, $gameday['Code_competition'], $events), $designations);
            $absent = self::absentOfficials(self::bilanOf($bilans, $id)?->answers ?? []);
            foreach (self::PROVIDED_ROLES as $role) {
                foreach ($effective->holders($role) as $holder) {
                    if (($role === 'ARBITRE_NJ' && $holder->authority !== Authority::Organisateur) || in_array($holder->matric, $absent, true)) {
                        continue;
                    }
                    $facts[] = new Fact($holder->club, Fact::OFFICIAL_PROVIDED, $role, 1, sprintf('%s · %s, licence %d', $gameday['Code_competition'], $gameday['Phase'] ?: $gameday['Nom'], $holder->matric), 'JOURNEE', (string) $id);
                }
            }
        }

        return $facts;
    }

    /**
     * Cartons jaunes, rouges et noirs des matchs validés, au club de l'équipe du joueur (annexe 8 § 2.2).
     *
     * @param list<int> $gamedayIds
     *
     * @return list<Fact>
     */
    private function cards(array $gamedayIds): array
    {
        if ($gamedayIds === []) {
            return [];
        }
        $facts = [];
        foreach ($this->connection->fetchAllAssociative(
            "SELECT m.Id, m.Numero_ordre, j.Code_competition, md.Id_evt_match AS code, COUNT(*) AS n,
                    CASE md.Equipe_A_B WHEN 'A' THEN ea.Code_club ELSE eb.Code_club END AS club
             FROM kp_match_detail md
             JOIN kp_match m ON m.Id = md.Id_match
             JOIN kp_journee j ON j.Id = m.Id_journee
             LEFT JOIN kp_competition_equipe ea ON ea.Id = m.Id_equipeA
             LEFT JOIN kp_competition_equipe eb ON eb.Id = m.Id_equipeB
             WHERE m.Id_journee IN (?) AND m.Validation = ? AND md.Id_evt_match IN ('J', 'R', 'D') AND md.Equipe_A_B IN ('A', 'B')
             GROUP BY m.Id, md.Id_evt_match, club",
            [$gamedayIds, self::VALIDATED],
            [ArrayParameterType::INTEGER],
        ) as $row) {
            $facts[] = new Fact($row['club'] ?: null, self::CARDS[$row['code']], '', (int) $row['n'], sprintf('%s · match n° %s', $row['Code_competition'], $row['Numero_ordre'] ?? $row['Id']), 'MATCH', (string) $row['Id']);
        }

        return $facts;
    }

    /**
     * Avertissements de tenue (club de l'arbitre) et infractions déclarés dans les bilans soumis.
     *
     * @param list<array{report: BilanAnswers, gamedays: list<int>}> $bilans
     *
     * @return list<Fact>
     */
    private function fromBilans(array $bilans): array
    {
        $facts = [];
        $clubs = $this->clubLabels();
        foreach ($bilans as ['report' => $report]) {
            $warned = array_values(array_filter(
                is_array($report->answers['referees'] ?? null) ? $report->answers['referees'] : [],
                static fn (array $row): bool => ($row['outfit_warning'] ?? false) === true && (int) ($row['matric'] ?? 0) > 0,
            ));
            $licenceClubs = $this->licenceClubs(array_map(static fn (array $row): int => (int) $row['matric'], $warned));
            foreach ($warned as $row) {
                $facts[] = new Fact($licenceClubs[(int) $row['matric']] ?? null, Fact::OUTFIT_WARNING, '', 1, sprintf('%s, licence %d', $row['name'] ?? '', $row['matric']), 'REPORT', (string) $report->id);
            }
            foreach (is_array($report->answers['infractions'] ?? null) ? $report->answers['infractions'] : [] as $row) {
                $offender = trim((string) ($row['offender'] ?? ''));
                $club = isset($clubs[$offender]) ? $offender : (array_search($offender, $clubs, true) ?: null);
                $facts[] = new Fact($club, Fact::INFRACTION, (string) ($row['category'] ?? ''), 1, trim($offender . ' — ' . ($row['description'] ?? ''), ' —'), 'REPORT', (string) $report->id);
            }
        }

        return $facts;
    }

    /**
     * Bilans de journée soumis, avec les journées qu'ils couvrent.
     *
     * @return list<array{report: BilanAnswers, gamedays: list<int>}>
     */
    private function bilans(string $season): array
    {
        $bilans = [];
        foreach ($this->connection->fetchAllAssociative(
            "SELECT id, perimetre_type, perimetre_id, reponses FROM kp_report WHERE saison = ? AND type = ? AND etat = ?",
            [$season, ReportTypeCatalog::BILAN_JOURNEE, Report::SUBMITTED],
        ) as $row) {
            $gamedays = $row['perimetre_type'] === 'EVENEMENT'
                ? array_map('intval', $this->connection->fetchFirstColumn('SELECT Id_journee FROM kp_evenement_journee WHERE Id_evenement = ?', [(int) $row['perimetre_id']]))
                : [(int) $row['perimetre_id']];
            $bilans[] = ['report' => new BilanAnswers((int) $row['id'], json_decode($row['reponses'] ?: '{}', true) ?: []), 'gamedays' => $gamedays];
        }

        return $bilans;
    }

    /** @param list<array{report: BilanAnswers, gamedays: list<int>}> $bilans */
    private static function bilanOf(array $bilans, int $gamedayId): ?BilanAnswers
    {
        foreach ($bilans as $bilan) {
            if (in_array($gamedayId, $bilan['gamedays'], true)) {
                return $bilan['report'];
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $answers
     *
     * @return list<int>
     */
    private static function absentOfficials(array $answers): array
    {
        $absent = [];
        foreach (is_array($answers['officials'] ?? null) ? $answers['officials'] : [] as $row) {
            if (in_array($row['presence'] ?? null, self::ABSENT, true) && (int) ($row['matric'] ?? 0) > 0) {
                $absent[] = (int) $row['matric'];
            }
        }

        return $absent;
    }

    /**
     * Terrains distincts utilisés par les matchs de chaque journée.
     *
     * @param list<int> $gamedayIds
     *
     * @return array<int, int>
     */
    private function pitches(array $gamedayIds): array
    {
        if ($gamedayIds === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            "SELECT Id_journee, COUNT(DISTINCT Terrain) AS n FROM kp_match WHERE Id_journee IN (?) AND Terrain IS NOT NULL AND Terrain <> '' GROUP BY Id_journee",
            [$gamedayIds],
            [ArrayParameterType::INTEGER],
        );

        return array_map('intval', array_column($rows, 'n', 'Id_journee'));
    }

    /**
     * @param list<int> $matrics
     *
     * @return array<int, string>
     */
    private function licenceClubs(array $matrics): array
    {
        if ($matrics === []) {
            return [];
        }

        return array_filter(array_column($this->connection->fetchAllAssociative(
            'SELECT Matric, Numero_club FROM kp_licence WHERE Matric IN (?)',
            [$matrics],
            [ArrayParameterType::INTEGER],
        ), 'Numero_club', 'Matric'));
    }

    /** @return array<string, string> */
    private function clubLabels(): array
    {
        return array_column($this->connection->fetchAllAssociative('SELECT Code, Libelle FROM kp_club'), 'Libelle', 'Code');
    }
}
