<?php

namespace App\Service;

use App\PublicResults\MatchLabelParser;
use Doctrine\DBAL\Connection;

/**
 * Schema of a competition (migrated from GestionSchema.php): its phases with their published rankings, pool teams and
 * games, for the app4 Schema page (AdminSchemaController) and its PDF (`schema`, PDF_GOTENBERG.md § 7.3).
 */
class CompetitionSchemaService
{
    /** A game whose team id is at most this one has no team yet: its placeholder is shown. */
    private const NO_TEAM_ID = 1;

    public function __construct(
        private readonly Connection $connection,
        private readonly MatchLabelParser $labelParser,
    ) {
    }

    /**
     * @return array{competition: array<string, mixed>, stages: int, totalMatches: int, phases: list<array<string, mixed>>}|null
     *         null for an unknown competition
     */
    public function schema(string $season, string $competition, string $lang, bool $includeEmpty = false): ?array
    {
        $compInfo = $this->loadCompetitionInfo($competition, $season);
        if ($compInfo === null) {
            return null;
        }

        $phases = $this->loadPhases($competition, $season, $includeEmpty);
        $phaseRankings = $this->loadPhaseRankings($competition, $season);
        $phaseMatches = $this->loadPhaseMatches($competition, $season, $lang);
        // Fallback for the phases without ranking.
        $poolTeams = $this->loadPoolTeams($competition, $season, $lang);

        $totalMatches = 0;
        $stageSet = [];
        $result = [];

        foreach ($phases as $phase) {
            $jId = (int) $phase['Id_journee'];
            $etape = (int) $phase['Etape'];
            $stageSet[$etape] = true;

            $ranking = $phaseRankings[$jId] ?? null;
            $pool = $poolTeams[$jId] ?? null;
            $matches = $phaseMatches[$jId] ?? [];

            // Start and end times from the games
            $startTime = null;
            $endTime = null;
            foreach ($matches as $m) {
                $heure = $m['heureMatch'] ?? null;
                if ($heure) {
                    if ($startTime === null) {
                        $startTime = $heure;
                    }
                    $endTime = $heure;
                }
            }

            $nbMatchs = (int) $phase['nb_matchs'];
            $totalMatches += $nbMatchs;

            $result[] = [
                'idJournee' => $jId,
                'phase' => $phase['Phase'] ?? '',
                'nom' => $phase['Nom'] ?? '',
                'etape' => $etape,
                'niveau' => (int) ($phase['Niveau'] ?? 0),
                'type' => $phase['Type'] ?? 'C',
                'nbequipes' => (int) ($phase['Nbequipes'] ?? 0),
                'dateDebut' => $phase['Date_debut'],
                'dateFin' => $phase['Date_fin'],
                'lieu' => $phase['Lieu'],
                'departement' => $phase['Departement'],
                'nbMatchs' => $nbMatchs,
                'startTime' => $startTime,
                'endTime' => $endTime,
                'ranking' => $ranking,
                'poolTeams' => $ranking === null ? $pool : null,
                'matches' => array_map(fn ($m) => [
                    'id' => (int) $m['Id'],
                    'numeroOrdre' => $m['Numero_ordre'] !== null ? (int) $m['Numero_ordre'] : null,
                    'equipeA' => $m['EquipeA'] ?? '',
                    'equipeB' => $m['EquipeB'] ?? '',
                    'scoreA' => $m['Validation'] === 'O' ? $m['ScoreA'] : null,
                    'scoreB' => $m['Validation'] === 'O' ? $m['ScoreB'] : null,
                    'idEquipeA' => (int) ($m['Id_equipeA'] ?? 0),
                    'idEquipeB' => (int) ($m['Id_equipeB'] ?? 0),
                ], $matches),
            ];
        }

        return [
            'competition' => $compInfo,
            'stages' => count($stageSet),
            'totalMatches' => $totalMatches,
            'phases' => $result,
        ];
    }

    private function loadCompetitionInfo(string $code, string $season): ?array
    {
        $sql = "SELECT Code, Code_saison, Libelle, Soustitre, Soustitre2,
                       Code_typeclt, Code_niveau, Code_ref, Titre_actif,
                       Qualifies, Elimines,
                       BandeauLink, Bandeau_actif,
                       LogoLink, Logo_actif,
                       SponsorLink, Sponsor_actif
                FROM kp_competition
                WHERE Code = ? AND Code_saison = ?";
        $row = $this->connection->prepare($sql)->executeQuery([$code, $season])->fetchAssociative();
        if (!$row) {
            return null;
        }

        return [
            'code' => $row['Code'],
            'season' => $row['Code_saison'],
            'libelle' => $row['Libelle'],
            'soustitre' => $row['Soustitre'] ?: null,
            'soustitre2' => $row['Soustitre2'] ?: null,
            'codeTypeclt' => $row['Code_typeclt'] ?: 'CHPT',
            'codeNiveau' => $row['Code_niveau'] ?: 'NAT',
            'codeRef' => $row['Code_ref'] ?: '',
            'titreActif' => $row['Titre_actif'] === 'O',
            'qualifies' => (int) $row['Qualifies'],
            'elimines' => (int) $row['Elimines'],
            'bandeauLink' => $this->buildImageLink($row['BandeauLink']),
            'bandeauActif' => ($row['Bandeau_actif'] ?? 'N') === 'O',
            'logoLink' => $this->buildImageLink($row['LogoLink']),
            'logoActif' => ($row['Logo_actif'] ?? 'N') === 'O',
            'sponsorLink' => $this->buildImageLink($row['SponsorLink']),
            'sponsorActif' => ($row['Sponsor_actif'] ?? 'N') === 'O',
        ];
    }

    /**
     * Load journées/phases with match counts, excluding Break/Pause.
     * By default empty phases (0 matches) are filtered out; pass $includeEmpty to keep them.
     */
    private function loadPhases(string $competition, string $season, bool $includeEmpty = false): array
    {
        $having = $includeEmpty ? '' : 'HAVING nb_matchs > 0';

        $sql = "SELECT j.Id AS Id_journee, j.Phase, j.Nom, j.Etape, j.Nbequipes, j.Niveau,
                       j.Type, j.Date_debut, j.Date_fin, j.Lieu, j.Departement,
                       COUNT(m.Id) AS nb_matchs
                FROM kp_journee j
                LEFT JOIN kp_match m ON j.Id = m.Id_journee
                WHERE j.Code_competition = ?
                AND j.Code_saison = ?
                AND (j.Phase IS NULL OR (j.Phase != 'Break' AND j.Phase != 'Pause'))
                GROUP BY j.Id
                $having
                ORDER BY j.Etape ASC, j.Niveau DESC, j.Date_debut DESC, j.Phase ASC";

        return $this->connection->prepare($sql)->executeQuery([$competition, $season])->fetchAllAssociative();
    }

    /**
     * Load published rankings per phase, keyed by journée ID.
     */
    private function loadPhaseRankings(string $competition, string $season): array
    {
        $sql = "SELECT ce.Id, ce.Libelle, ce.Code_club, cej.Id_journee,
                       cej.Clt_publi, cej.Pts_publi, cej.J_publi, cej.Diff_publi
                FROM kp_competition_equipe ce
                INNER JOIN kp_competition_equipe_journee cej ON ce.Id = cej.Id
                INNER JOIN kp_journee j ON cej.Id_journee = j.Id
                WHERE j.Code_competition = ?
                AND j.Code_saison = ?
                ORDER BY j.Id, cej.Clt_publi ASC, cej.Diff_publi DESC, cej.Plus_publi ASC";

        $rows = $this->connection->prepare($sql)->executeQuery([$competition, $season])->fetchAllAssociative();

        $result = [];
        foreach ($rows as $r) {
            $jId = (int) $r['Id_journee'];
            $result[$jId][] = [
                'id' => (int) $r['Id'],
                'libelle' => $r['Libelle'],
                'codeClub' => $r['Code_club'] ?: '',
                'clt' => (int) $r['Clt_publi'],
                'pts' => (int) $r['Pts_publi'] / 100,
                'j' => (int) $r['J_publi'],
                'diff' => (int) $r['Diff_publi'],
            ];
        }

        return $result;
    }

    /**
     * Load matches per phase, keyed by journée ID.
     */
    private function loadPhaseMatches(string $competition, string $season, string $lang): array
    {
        $sql = "SELECT m.Id, m.Id_journee, m.Numero_ordre, m.Date_match, m.Heure_match,
                       m.Libelle, m.Validation, m.ScoreA, m.ScoreB,
                       m.Id_equipeA, m.Id_equipeB,
                       ce1.Libelle AS EquipeA, ce2.Libelle AS EquipeB
                FROM kp_journee j
                INNER JOIN kp_match m ON m.Id_journee = j.Id
                LEFT JOIN kp_competition_equipe ce1 ON m.Id_equipeA = ce1.Id
                LEFT JOIN kp_competition_equipe ce2 ON m.Id_equipeB = ce2.Id
                WHERE j.Code_competition = ?
                AND j.Code_saison = ?
                ORDER BY j.Etape ASC, j.Niveau DESC, m.Date_match, m.Heure_match, m.Numero_ordre";

        $rows = $this->connection->prepare($sql)->executeQuery([$competition, $season])->fetchAllAssociative();

        $result = [];
        foreach ($rows as $r) {
            $r = $this->withPlaceholders($r, $lang);
            $r['heureMatch'] = $r['Heure_match'];
            $result[(int) $r['Id_journee']][] = $r;
        }

        return $result;
    }

    /**
     * Load pool team compositions for type C phases (fallback when no ranking exists).
     * Teams are extracted from matches and deduplicated.
     */
    private function loadPoolTeams(string $competition, string $season, string $lang): array
    {
        $sql = "SELECT j.Id AS Id_journee, m.Id_equipeA, m.Id_equipeB, m.Libelle,
                       ce1.Libelle AS EquipeA, ce2.Libelle AS EquipeB,
                       ce1.Tirage AS TirageA, ce2.Tirage AS TirageB
                FROM kp_journee j
                INNER JOIN kp_match m ON m.Id_journee = j.Id
                LEFT JOIN kp_competition_equipe ce1 ON m.Id_equipeA = ce1.Id
                LEFT JOIN kp_competition_equipe ce2 ON m.Id_equipeB = ce2.Id
                WHERE j.Type = 'C'
                AND j.Code_competition = ?
                AND j.Code_saison = ?
                ORDER BY j.Etape ASC, j.Niveau DESC, j.Id ASC";

        $rows = $this->connection->prepare($sql)->executeQuery([$competition, $season])->fetchAllAssociative();

        $result = [];
        foreach ($rows as $r) {
            $jId = (int) $r['Id_journee'];
            $idA = (int) ($r['Id_equipeA'] ?? 0);
            $idB = (int) ($r['Id_equipeB'] ?? 0);
            $r = $this->withPlaceholders($r, $lang);

            if (!isset($result[$jId])) {
                $result[$jId] = [];
            }

            // Deduplicate by team name
            if ($idA > self::NO_TEAM_ID && !isset($result[$jId][$r['EquipeA']])) {
                $result[$jId][$r['EquipeA']] = [
                    'id' => $idA,
                    'libelle' => $r['EquipeA'],
                    'tirage' => (int) ($r['TirageA'] ?? 0),
                ];
            }
            if ($idB > self::NO_TEAM_ID && !isset($result[$jId][$r['EquipeB']])) {
                $result[$jId][$r['EquipeB']] = [
                    'id' => $idB,
                    'libelle' => $r['EquipeB'],
                    'tirage' => (int) ($r['TirageB'] ?? 0),
                ];
            }
        }

        // Convert to indexed arrays sorted by tirage
        $final = [];
        foreach ($result as $jId => $teams) {
            $arr = array_values($teams);
            usort($arr, fn ($a, $b) => $a['tirage'] <=> $b['tirage']);
            $final[$jId] = $arr;
        }

        return $final;
    }

    /**
     * A game without its teams yet takes their placeholders (« (1er Poule A) », « (Winner game #12) »).
     *
     * @param array<string, mixed> $game
     *
     * @return array<string, mixed>
     */
    private function withPlaceholders(array $game, string $lang): array
    {
        $idA = (int) ($game['Id_equipeA'] ?? 0);
        $idB = (int) ($game['Id_equipeB'] ?? 0);
        if ($idA > self::NO_TEAM_ID && $idB > self::NO_TEAM_ID) {
            return $game;
        }
        $parsed = $this->labelParser->parse((string) ($game['Libelle'] ?? ''), $lang);
        if ($idA <= self::NO_TEAM_ID && isset($parsed[0])) {
            $game['EquipeA'] = $parsed[0];
        }
        if ($idB <= self::NO_TEAM_ID && isset($parsed[1])) {
            $game['EquipeB'] = $parsed[1];
        }

        return $game;
    }

    private function buildImageLink(?string $dbValue): ?string
    {
        if (empty($dbValue)) {
            return null;
        }
        if (str_starts_with($dbValue, 'http://') || str_starts_with($dbValue, 'https://')) {
            return $dbValue;
        }

        return "/img/logo/{$dbValue}";
    }
}
