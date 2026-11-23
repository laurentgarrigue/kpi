<?php

namespace App\Designation;

use App\Competition\CompetitionLevel;
use Doctrine\DBAL\Connection;

/** Comptes KPI et mandats générés par les Désignations (FEATURE_DESIGNATIONS.md D5, D6, D10 ; ticket 06). */
final class MandateRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array{niveau: int, mail: string}|null compte dont le code est la licence (D5) */
    public function account(int $matric): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT Niveau, Mail FROM kp_user WHERE Code = ?', [(string) $matric]);

        return $row === false ? null : ['niveau' => (int) $row['Niveau'], 'mail' => (string) $row['Mail']];
    }

    /** Compte de consultation (profil 8), sans filtre ni mot de passe utilisable : l'activation passe par le lien. */
    public function createAccount(int $matric, string $identity, string $mail, int $profile, string $unusablePasswordHash): void
    {
        $this->connection->insert('kp_user', [
            'Code' => (string) $matric,
            'Pwd' => $unusablePasswordHash,
            'Identite' => mb_substr($identity, 0, 80),
            'Mail' => $mail,
            'Tel' => '',
            'Fonction' => '',
            'Niveau' => $profile,
            'Filtre_competition' => '',
            'Filtre_saison' => '',
            'Filtre_competition_sql' => '',
            'Filtre_journee' => '',
        ]);
    }

    public function storeResetToken(int $matric, string $token): void
    {
        $this->connection->delete('kp_user_token', ['user' => (string) $matric]);
        $this->connection->executeStatement('INSERT INTO kp_user_token (user, token, generated_at) VALUES (?, ?, NOW())', [(string) $matric, $token]);
    }

    /** @return list<Designation> */
    public function designationsOf(int $matric, string $season, string $role): array
    {
        return array_map(DesignationRepository::hydrate(...), $this->connection->fetchAllAssociative(
            'SELECT * FROM kp_designation WHERE matric = ? AND saison = ? AND role = ?',
            [$matric, $season, $role],
        ));
    }

    /**
     * Désignations à rafraîchir quand une journée change d'événement ou de compétition (D10).
     *
     * @param list<int> $eventIds
     *
     * @return list<Designation>
     */
    public function designationsAround(string $season, string $competitionCode, int $gamedayId, array $eventIds): array
    {
        $sql = "SELECT * FROM kp_designation WHERE saison = ? AND (
                    (perimetre_type = 'COMPETITION' AND perimetre_id = ?) OR (perimetre_type = 'JOURNEE' AND perimetre_id = ?)";
        $params = [$season, $competitionCode, (string) $gamedayId];
        if ($eventIds !== []) {
            $sql .= sprintf(" OR (perimetre_type = 'EVENEMENT' AND perimetre_id IN (%s))", implode(',', array_fill(0, count($eventIds), '?')));
            array_push($params, ...array_map('strval', $eventIds));
        }

        return array_map(DesignationRepository::hydrate(...), $this->connection->fetchAllAssociative($sql . ')', $params));
    }

    /**
     * @param list<int> $gamedayIds
     *
     * @return array<int, string> journée => compétition
     */
    public function competitionsOfGamedays(array $gamedayIds): array
    {
        if ($gamedayIds === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT Id, Code_competition FROM kp_journee WHERE Id IN (%s)', implode(',', array_fill(0, count($gamedayIds), '?'))),
            $gamedayIds,
        );

        return array_column($rows, 'Code_competition', 'Id');
    }

    /**
     * @param list<string> $competitions
     *
     * @return array<int, string> journée => compétition
     */
    public function gamedaysOfCompetitions(string $season, array $competitions): array
    {
        if ($competitions === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT Id, Code_competition FROM kp_journee WHERE Code_saison = ? AND Code_competition IN (%s)', implode(',', array_fill(0, count($competitions), '?'))),
            [$season, ...$competitions],
        );

        return array_column($rows, 'Code_competition', 'Id');
    }

    /**
     * @param list<int> $teamIds
     *
     * @return list<array{competition: string, club: string|null}>
     */
    public function teams(array $teamIds): array
    {
        if ($teamIds === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT Code_compet, Code_club FROM kp_competition_equipe WHERE Id IN (%s)', implode(',', array_fill(0, count($teamIds), '?'))),
            $teamIds,
        );

        return array_map(static fn (array $row): array => ['competition' => $row['Code_compet'], 'club' => $row['Code_club'] ?: null], $rows);
    }

    /** @return list<string> compétitions nationales de la saison (niveau national, D16) */
    public function nationalCompetitions(string $season): array
    {
        $codes = $this->connection->fetchFirstColumn('SELECT Code FROM kp_competition WHERE Code_saison = ?', [$season]);

        return array_values(array_filter($codes, static fn (string $code): bool => CompetitionLevel::isNational($code)));
    }

    /** @param array<string, string|null> $filters colonnes de {@see MandatePerimeter::columns()} */
    public function saveGeneratedMandate(int $matric, string $season, string $role, string $label, int $profile, array $filters): void
    {
        $key = ['user_code' => (string) $matric, 'designation_saison' => $season, 'designation_role' => $role];
        $values = ['libelle' => $label, 'niveau' => $profile] + $filters;
        $id = $this->connection->fetchOne(
            'SELECT id FROM kp_user_mandat WHERE user_code = ? AND designation_saison = ? AND designation_role = ?',
            array_values($key),
        );
        if ($id === false) {
            $this->connection->insert('kp_user_mandat', $key + $values);
        } else {
            $this->connection->update('kp_user_mandat', $values, ['id' => $id]);
        }
    }

    public function deleteGeneratedMandate(int $matric, string $season, string $role): void
    {
        $this->connection->delete('kp_user_mandat', ['user_code' => (string) $matric, 'designation_saison' => $season, 'designation_role' => $role]);
    }

    /** @return array{nom: string|null, prenom: string|null}|null */
    public function licenceName(int $matric): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT Nom, Prenom FROM kp_licence WHERE Matric = ?', [$matric]);

        return $row === false ? null : ['nom' => $row['Nom'], 'prenom' => $row['Prenom']];
    }
}
