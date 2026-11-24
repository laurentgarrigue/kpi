<?php

namespace App\Report;

use Doctrine\DBAL\Connection;

/**
 * Recherche de l'élément auquel rattacher un nouveau rapport (FEATURE_DESIGNATIONS.md D12, D14) : événements,
 * compétitions, journées et matchs d'une saison dont le libellé, le code, le lieu, la phase, les équipes ou le numéro
 * correspondent au texte saisi. Les droits de rédaction sont vérifiés ensuite, à la création.
 */
final class ReportScopeSearch
{
    public const MIN_LENGTH = 2;
    private const LIMIT = 15;

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<ReportScope> $scopes types cherchés (tous si vide)
     *
     * @return list<array{scopeType: string, scopeId: string, label: string}>
     */
    public function search(string $season, string $query, array $scopes): array
    {
        $query = trim($query);
        // un numéro de match peut tenir en un chiffre : la longueur minimale ne vaut que pour un texte
        if ($query === '' || (!ctype_digit($query) && mb_strlen($query) < self::MIN_LENGTH)) {
            return [];
        }
        $like = '%' . addcslashes($query, '%_\\') . '%';
        $wanted = static fn (ReportScope $scope): bool => $scopes === [] || in_array($scope, $scopes, true);
        $results = [];
        if ($wanted(ReportScope::Evenement)) {
            foreach ($this->connection->fetchAllAssociative(
                'SELECT DISTINCT e.Id, e.Libelle, e.Lieu, e.Date_debut FROM kp_evenement e
                 JOIN kp_evenement_journee ej ON ej.Id_evenement = e.Id JOIN kp_journee j ON j.Id = ej.Id_journee
                 WHERE j.Code_saison = ? AND (e.Libelle LIKE ? OR e.Lieu LIKE ?) ORDER BY e.Date_debut DESC LIMIT ' . self::LIMIT,
                [$season, $like, $like],
            ) as $row) {
                $results[] = self::item(ReportScope::Evenement, $row['Id'], [$row['Libelle'], $row['Lieu'], $row['Date_debut']]);
            }
        }
        if ($wanted(ReportScope::Competition)) {
            foreach ($this->connection->fetchAllAssociative(
                'SELECT Code, Libelle FROM kp_competition WHERE Code_saison = ? AND (Code LIKE ? OR Libelle LIKE ?) ORDER BY Code LIMIT ' . self::LIMIT,
                [$season, $like, $like],
            ) as $row) {
                $results[] = self::item(ReportScope::Competition, $row['Code'], [$row['Code'], $row['Libelle']]);
            }
        }
        if ($wanted(ReportScope::Journee)) {
            foreach ($this->connection->fetchAllAssociative(
                'SELECT Id, Code_competition, Phase, Nom, Lieu, Date_debut FROM kp_journee
                 WHERE Code_saison = ? AND (Code_competition LIKE ? OR Phase LIKE ? OR Nom LIKE ? OR Lieu LIKE ?)
                 ORDER BY Date_debut, Id LIMIT ' . self::LIMIT,
                [$season, $like, $like, $like, $like],
            ) as $row) {
                $results[] = self::item(ReportScope::Journee, $row['Id'], [$row['Code_competition'], $row['Phase'] ?: $row['Nom'], $row['Date_debut'], $row['Lieu']]);
            }
        }
        if ($wanted(ReportScope::Match)) {
            $number = ctype_digit($query) ? (int) $query : -1;
            foreach ($this->connection->fetchAllAssociative(
                'SELECT m.Id, m.Numero_ordre, m.Date_match, m.Heure_match, j.Code_competition, ea.Libelle AS team_a, eb.Libelle AS team_b
                 FROM kp_match m JOIN kp_journee j ON j.Id = m.Id_journee
                 LEFT JOIN kp_competition_equipe ea ON ea.Id = m.Id_equipeA
                 LEFT JOIN kp_competition_equipe eb ON eb.Id = m.Id_equipeB
                 WHERE j.Code_saison = ? AND (ea.Libelle LIKE ? OR eb.Libelle LIKE ? OR m.Numero_ordre = ?)
                 ORDER BY (m.Numero_ordre = ?) DESC, m.Date_match, m.Heure_match, m.Id LIMIT ' . self::LIMIT,
                // un numéro de match exact passe avant les équipes dont le nom contient ce chiffre
                [$season, $like, $like, $number, $number],
            ) as $row) {
                $results[] = self::item(ReportScope::Match, $row['Id'], [
                    $row['Code_competition'],
                    'n° ' . ($row['Numero_ordre'] ?? $row['Id']),
                    sprintf('%s - %s', $row['team_a'] ?? '?', $row['team_b'] ?? '?'),
                    trim(($row['Date_match'] ?? '') . ' ' . ($row['Heure_match'] ?? '')),
                ]);
            }
        }

        return $results;
    }

    /**
     * @param list<mixed> $parts
     *
     * @return array{scopeType: string, scopeId: string, label: string}
     */
    private static function item(ReportScope $scope, mixed $id, array $parts): array
    {
        return [
            'scopeType' => $scope->value,
            'scopeId' => (string) $id,
            'label' => implode(' · ', array_filter(array_map(static fn (mixed $part): string => trim((string) $part), $parts), static fn (string $part): bool => $part !== '')),
        ];
    }
}
