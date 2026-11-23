<?php

namespace App\Report;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Lecture du périmètre d'un rapport (compétition, événement, journée, match) dans la base KPI. */
final class ReportPerimeters
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function resolve(ReportScope $scope, string $scopeId, string $season): ?ReportPerimeter
    {
        return match ($scope) {
            ReportScope::Match => $this->match((int) $scopeId),
            ReportScope::Journee => $this->gamedays($scope, $scopeId, $season, 'j.Id = ?', [(int) $scopeId]),
            ReportScope::Competition => $this->gamedays($scope, $scopeId, $season, 'j.Code_competition = ? AND j.Code_saison = ?', [$scopeId, $season], true),
            ReportScope::Evenement => $this->gamedays($scope, $scopeId, $season, 'j.Id IN (SELECT Id_journee FROM kp_evenement_journee WHERE Id_evenement = ?) AND j.Code_saison = ?', [(int) $scopeId, $season]),
        };
    }

    /** @param list<mixed> $params */
    private function gamedays(ReportScope $scope, string $scopeId, string $season, string $where, array $params, bool $mayBeEmpty = false): ?ReportPerimeter
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT j.Id, j.Code_competition, j.Code_saison, j.Nom, j.Phase, j.Lieu, j.Date_debut, j.Date_fin, c.Libelle AS competition
             FROM kp_journee j LEFT JOIN kp_competition c ON c.Code = j.Code_competition AND c.Code_saison = j.Code_saison
             WHERE $where ORDER BY j.Date_debut, j.Id",
            $params,
        );
        if ($rows === [] && !($mayBeEmpty && $this->competitionExists($scopeId, $season))) {
            return null;
        }
        $season = $rows[0]['Code_saison'] ?? $season;
        $ids = array_map(static fn (array $row): int => (int) $row['Id'], $rows);
        $starts = array_filter(array_column($rows, 'Date_debut'));
        $ends = array_filter(array_map(static fn (array $row): ?string => $row['Date_fin'] ?: $row['Date_debut'], $rows));
        $label = match ($scope) {
            ReportScope::Journee => sprintf('%s · %s', $rows[0]['Code_competition'], $rows[0]['Phase'] ?: $rows[0]['Nom']),
            ReportScope::Evenement => (string) ($this->connection->fetchOne('SELECT Libelle FROM kp_evenement WHERE Id = ?', [(int) $scopeId]) ?: $scopeId),
            default => sprintf('%s · %s', $scopeId, ($rows[0] ?? [])['competition'] ?? $this->connection->fetchOne('SELECT Libelle FROM kp_competition WHERE Code = ? AND Code_saison = ?', [$scopeId, $season])),
        };

        return new ReportPerimeter(
            $scope,
            $scopeId,
            $season,
            array_column($rows, 'Code_competition', 'Id'),
            $this->events($ids),
            $ids === [] ? [] : array_map('intval', $this->connection->fetchFirstColumn('SELECT Id FROM kp_match WHERE Id_journee IN (?)', [$ids], [ArrayParameterType::INTEGER])),
            $ends === [] ? null : max($ends),
            trim($label, ' ·'),
            [
                'label' => trim($label, ' ·'),
                'competitions' => array_values(array_unique(array_filter(array_map(static fn (array $row): string => trim($row['Code_competition'] . ' - ' . ($row['competition'] ?? ''), ' -'), $rows)))),
                'start' => $starts === [] ? null : min($starts),
                'end' => $ends === [] ? null : max($ends),
                'places' => array_values(array_unique(array_filter(array_column($rows, 'Lieu')))),
            ],
        );
    }

    private function match(int $matchId): ?ReportPerimeter
    {
        $row = $this->connection->fetchAssociative(
            "SELECT m.Id, m.Numero_ordre, m.Date_match, m.Heure_match, m.Terrain, m.ScoreA, m.ScoreB, m.Id_journee,
                    m.Arbitre_principal, m.Arbitre_secondaire,
                    j.Code_competition, j.Code_saison, j.Lieu, ea.Libelle AS team_a, eb.Libelle AS team_b
             FROM kp_match m JOIN kp_journee j ON j.Id = m.Id_journee
             LEFT JOIN kp_competition_equipe ea ON ea.Id = m.Id_equipeA
             LEFT JOIN kp_competition_equipe eb ON eb.Id = m.Id_equipeB
             WHERE m.Id = ?",
            [$matchId],
        );
        if ($row === false) {
            return null;
        }
        $gamedayId = (int) $row['Id_journee'];
        $label = sprintf('%s · n° %s · %s - %s', $row['Code_competition'], $row['Numero_ordre'] ?? $matchId, $row['team_a'] ?? '?', $row['team_b'] ?? '?');

        return new ReportPerimeter(
            ReportScope::Match,
            (string) $matchId,
            $row['Code_saison'],
            [$gamedayId => $row['Code_competition']],
            $this->events([$gamedayId]),
            [$matchId],
            $row['Date_match'],
            $label,
            [
                'label' => $label,
                'competitions' => [$row['Code_competition']],
                'start' => $row['Date_match'],
                'end' => $row['Date_match'],
                'places' => array_values(array_filter([$row['Lieu']])),
                'match' => [
                    'number' => $row['Numero_ordre'],
                    'time' => $row['Heure_match'],
                    'pitch' => $row['Terrain'],
                    'teams' => [$row['team_a'], $row['team_b']],
                    'score' => [$row['ScoreA'], $row['ScoreB']],
                    'referees' => array_values(array_filter([$row['Arbitre_principal'], $row['Arbitre_secondaire']])),
                ],
            ],
        );
    }

    /**
     * @param list<int> $gamedayIds
     *
     * @return array<int, list<int>>
     */
    private function events(array $gamedayIds): array
    {
        $events = array_fill_keys($gamedayIds, []);
        if ($gamedayIds === []) {
            return $events;
        }
        foreach ($this->connection->fetchAllAssociative('SELECT Id_journee, Id_evenement FROM kp_evenement_journee WHERE Id_journee IN (?)', [$gamedayIds], [ArrayParameterType::INTEGER]) as $row) {
            $events[(int) $row['Id_journee']][] = (int) $row['Id_evenement'];
        }

        return $events;
    }

    private function competitionExists(string $code, string $season): bool
    {
        return (bool) $this->connection->fetchOne('SELECT 1 FROM kp_competition WHERE Code = ? AND Code_saison = ?', [$code, $season]);
    }

    /**
     * Licences des arbitres désignés des matchs (D15).
     *
     * @param list<int> $matchIds
     *
     * @return list<int>
     */
    public function referees(array $matchIds): array
    {
        if ($matchIds === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT Matric_arbitre_principal AS a, Matric_arbitre_secondaire AS b FROM kp_match WHERE Id IN (?)',
            [$matchIds],
            [ArrayParameterType::INTEGER],
        );
        $matrics = [];
        foreach ($rows as $row) {
            foreach ([$row['a'], $row['b']] as $matric) {
                if ((int) $matric > 0) {
                    $matrics[] = (int) $matric;
                }
            }
        }

        return array_values(array_unique($matrics));
    }
}
