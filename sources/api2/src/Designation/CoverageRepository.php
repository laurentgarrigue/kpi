<?php

namespace App\Designation;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Lectures du suivi des Désignations (FEATURE_DESIGNATIONS.md § 4, ticket 07). */
final class CoverageRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** Même ordre que le site public : section et ordre du groupe, tour, ordre dans le groupe. @return list<array{code: string, label: string, type: string}> type = Code_typeclt (CHPT, CP, MULTI…) */
    public function competitions(string $season): array
    {
        return array_map(
            static fn (array $row): array => ['code' => $row['Code'], 'label' => (string) ($row['Libelle'] ?? $row['Code']), 'type' => (string) ($row['Code_typeclt'] ?? '')],
            $this->connection->fetchAllAssociative('SELECT c.Code, c.Libelle, c.Code_typeclt FROM kp_competition c LEFT JOIN kp_groupe g ON g.Groupe = c.Code_ref
                 WHERE c.Code_saison = ? ORDER BY COALESCE(g.section, 999), COALESCE(g.ordre, 999), c.Code_tour, c.GroupOrder, c.Code', [$season]),
        );
    }

    /**
     * @param list<string> $competitions
     *
     * @return list<array{id: int, competition: string, label: string, place: string, start: string|null, end: string|null, eventIds: list<int>}>
     */
    public function gamedays(string $season, array $competitions): array
    {
        if ($competitions === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            "SELECT j.Id, j.Code_competition, j.Phase, j.Nom, j.Lieu, j.Date_debut, j.Date_fin,
                    GROUP_CONCAT(ej.Id_evenement ORDER BY ej.Id_evenement) AS events
             FROM kp_journee j LEFT JOIN kp_evenement_journee ej ON ej.Id_journee = j.Id
             WHERE j.Code_saison = ? AND j.Code_competition IN (?)
             GROUP BY j.Id ORDER BY j.Date_debut, j.Id",
            [$season, $competitions],
            [1 => ArrayParameterType::STRING],
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['Id'],
            'competition' => $row['Code_competition'],
            'label' => (string) ($row['Phase'] ?: ($row['Nom'] ?: $row['Id'])),
            'place' => (string) ($row['Lieu'] ?? ''),
            'start' => $row['Date_debut'],
            'end' => $row['Date_fin'],
            'eventIds' => $row['events'] === null ? [] : array_map('intval', explode(',', $row['events'])),
        ], $rows);
    }

    /**
     * @param list<string> $competitions
     *
     * @return list<array{id: int, competition: string, label: string, club: string|null}>
     */
    public function teams(string $season, array $competitions): array
    {
        if ($competitions === []) {
            return [];
        }

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['Id'],
            'competition' => $row['Code_compet'],
            'label' => (string) $row['Libelle'],
            'club' => $row['Code_club'] ?: null,
        ], $this->connection->fetchAllAssociative(
            'SELECT Id, Code_compet, Libelle, Code_club FROM kp_competition_equipe WHERE Code_saison = ? AND Code_compet IN (?) ORDER BY Code_compet, Libelle',
            [$season, $competitions],
            [1 => ArrayParameterType::STRING],
        ));
    }

    /**
     * @param list<string> $codes
     *
     * @return array<string, string> code => libellé
     */
    public function clubLabels(array $codes): array
    {
        if ($codes === []) {
            return [];
        }

        return array_column($this->connection->fetchAllAssociative(
            'SELECT Code, Libelle FROM kp_club WHERE Code IN (?)',
            [$codes],
            [ArrayParameterType::STRING],
        ), 'Libelle', 'Code');
    }

    /**
     * @param list<int> $matrics
     *
     * @return array<int, array{name: string, hasEmail: bool}>
     */
    public function people(array $matrics): array
    {
        if ($matrics === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            "SELECT l.Matric, l.Nom, l.Prenom, u.Mail FROM kp_licence l LEFT JOIN kp_user u ON u.Code = CAST(l.Matric AS CHAR) WHERE l.Matric IN (?)",
            [$matrics],
            [ArrayParameterType::INTEGER],
        );
        $people = [];
        foreach ($rows as $row) {
            $people[(int) $row['Matric']] = [
                'name' => trim(($row['Nom'] ?? '') . ' ' . ($row['Prenom'] ?? '')),
                'hasEmail' => ($row['Mail'] ?? '') !== '',
            ];
        }

        return $people;
    }
}
