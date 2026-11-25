<?php

namespace App\Designation;

use Doctrine\DBAL\Connection;

/** Accès à kp_designation et aux périmètres qu'elle référence (FEATURE_DESIGNATIONS.md § 8.1). */
final class DesignationRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param array{role?: string, scopeType?: string, scopeId?: string, matric?: int, club?: string} $filters
     *
     * @return list<array<string, mixed>> lignes avec Nom et Prenom du licencié
     */
    public function search(string $season, array $filters): array
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('d.*', 'l.Nom AS nom', 'l.Prenom AS prenom')
            ->from('kp_designation', 'd')
            ->leftJoin('d', 'kp_licence', 'l', 'l.Matric = d.matric')
            ->where('d.saison = :season')
            ->setParameter('season', $season)
            ->orderBy('d.role')->addOrderBy('d.perimetre_type')->addOrderBy('d.perimetre_id')->addOrderBy('d.ordre')->addOrderBy('d.id');
        $columns = ['role' => 'd.role', 'scopeType' => 'd.perimetre_type', 'scopeId' => 'd.perimetre_id', 'matric' => 'd.matric', 'club' => 'd.club'];
        foreach ($columns as $filter => $column) {
            if (isset($filters[$filter])) {
                $qb->andWhere("$column = :$filter")->setParameter($filter, $filters[$filter]);
            }
        }

        return $qb->executeQuery()->fetchAllAssociative();
    }

    /** @return array<string, mixed>|null */
    public function findRow(int $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT d.*, l.Nom AS nom, l.Prenom AS prenom FROM kp_designation d LEFT JOIN kp_licence l ON l.Matric = d.matric WHERE d.id = ?',
            [$id],
        );

        return $row === false ? null : $row;
    }

    public function find(int $id): ?Designation
    {
        $row = $this->findRow($id);

        return $row === null ? null : self::hydrate($row);
    }

    /** @return list<Designation> */
    public function ofSeason(string $season, ?string $role = null): array
    {
        $sql = 'SELECT * FROM kp_designation WHERE saison = ?';
        $params = [$season];
        if ($role !== null) {
            $sql .= ' AND role = ?';
            $params[] = $role;
        }

        return array_map(self::hydrate(...), $this->connection->fetchAllAssociative($sql, $params));
    }

    public function countHolders(string $season, string $role, ScopeType $scopeType, string $scopeId): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM kp_designation WHERE saison = ? AND role = ? AND perimetre_type = ? AND perimetre_id = ?',
            [$season, $role, $scopeType->value, $scopeId],
        );
    }

    public function exists(string $season, string $role, ScopeType $scopeType, string $scopeId, int $matric): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM kp_designation WHERE saison = ? AND role = ? AND perimetre_type = ? AND perimetre_id = ? AND matric = ?',
            [$season, $role, $scopeType->value, $scopeId, $matric],
        );
    }

    /** @return array{club: string|null}|null null si la licence n'existe pas */
    public function licence(int $matric): ?array
    {
        $club = $this->connection->fetchAssociative('SELECT Numero_club FROM kp_licence WHERE Matric = ?', [$matric]);

        return $club === false ? null : ['club' => ($club['Numero_club'] ?? '') === '' ? null : $club['Numero_club']];
    }

    public function scopeExists(string $season, ScopeType $scopeType, string $scopeId): bool
    {
        $sql = match ($scopeType) {
            ScopeType::Niveau => null,
            ScopeType::Competition => 'SELECT 1 FROM kp_competition WHERE Code = ? AND Code_saison = ?',
            ScopeType::Equipe => 'SELECT 1 FROM kp_competition_equipe WHERE Id = ? AND Code_saison = ?',
            ScopeType::Journee => 'SELECT 1 FROM kp_journee WHERE Id = ? AND Code_saison = ?',
            // un événement n'a pas de saison : il doit contenir au moins une journée de la saison
            ScopeType::Evenement => 'SELECT 1 FROM kp_evenement_journee ej JOIN kp_journee j ON j.Id = ej.Id_journee WHERE ej.Id_evenement = ? AND j.Code_saison = ? LIMIT 1',
        };
        if ($sql === null) {
            return $scopeId === Designation::NATIONAL_LEVEL;
        }

        return (bool) $this->connection->fetchOne($sql, [$scopeId, $season]);
    }

    /**
     * Compétitions couvertes par un périmètre, pour le respect du périmètre du mandat de l'utilisateur. Le niveau
     * national n'est rattaché à aucune compétition.
     *
     * @return list<string>
     */
    public function competitionsOf(string $season, ScopeType $scopeType, string $scopeId): array
    {
        return match ($scopeType) {
            ScopeType::Niveau => [],
            ScopeType::Competition => [$scopeId],
            ScopeType::Equipe => $this->connection->fetchFirstColumn('SELECT Code_compet FROM kp_competition_equipe WHERE Id = ? AND Code_saison = ?', [$scopeId, $season]),
            ScopeType::Journee => $this->connection->fetchFirstColumn('SELECT Code_competition FROM kp_journee WHERE Id = ?', [$scopeId]),
            ScopeType::Evenement => $this->connection->fetchFirstColumn(
                'SELECT DISTINCT j.Code_competition FROM kp_evenement_journee ej JOIN kp_journee j ON j.Id = ej.Id_journee WHERE ej.Id_evenement = ? AND j.Code_saison = ?',
                [$scopeId, $season],
            ),
        };
    }

    public function gameday(int $gamedayId): ?GamedayScope
    {
        $row = $this->connection->fetchAssociative('SELECT Code_competition, Code_saison FROM kp_journee WHERE Id = ?', [$gamedayId]);
        if ($row === false) {
            return null;
        }
        $eventIds = array_map('intval', $this->connection->fetchFirstColumn(
            'SELECT Id_evenement FROM kp_evenement_journee WHERE Id_journee = ?',
            [$gamedayId],
        ));

        return new GamedayScope($gamedayId, $row['Code_saison'], $row['Code_competition'], $eventIds);
    }

    /**
     * Journées couvertes par un périmètre (D9) : une journée, les journées d'une compétition ou d'un événement dans la
     * saison. Niveau et équipe ne couvrent aucune journée pour la recopie legacy.
     *
     * @return list<int>
     */
    public function gamedayIdsOf(string $season, ScopeType $scopeType, string $scopeId): array
    {
        $sql = match ($scopeType) {
            ScopeType::Journee => 'SELECT Id FROM kp_journee WHERE Id = ? AND Code_saison = ?',
            ScopeType::Competition => 'SELECT Id FROM kp_journee WHERE Code_competition = ? AND Code_saison = ?',
            ScopeType::Evenement => 'SELECT j.Id FROM kp_evenement_journee ej JOIN kp_journee j ON j.Id = ej.Id_journee WHERE ej.Id_evenement = ? AND j.Code_saison = ?',
            ScopeType::Niveau, ScopeType::Equipe => null,
        };

        return $sql === null ? [] : array_map('intval', $this->connection->fetchFirstColumn($sql, [$scopeId, $season]));
    }

    /**
     * Rôles des Désignations posées sur la journée, sa compétition ou les événements donnés.
     *
     * @param list<int> $eventIds
     *
     * @return list<string>
     */
    public function rolesAround(GamedayScope $gameday, array $eventIds): array
    {
        $sql = "SELECT DISTINCT role FROM kp_designation WHERE saison = ? AND (
                    (perimetre_type = 'JOURNEE' AND perimetre_id = ?)
                    OR (perimetre_type = 'COMPETITION' AND perimetre_id = ?)";
        $params = [$gameday->season, (string) $gameday->gamedayId, $gameday->competitionCode];
        if ($eventIds !== []) {
            $sql .= sprintf(" OR (perimetre_type = 'EVENEMENT' AND perimetre_id IN (%s))", implode(',', array_fill(0, count($eventIds), '?')));
            array_push($params, ...array_map('strval', $eventIds));
        }

        return $this->connection->fetchFirstColumn($sql . ') ORDER BY role', $params);
    }

    /**
     * Libellé legacy « NOM Prénom (licence) » de chaque licence.
     *
     * @param list<int> $matrics
     *
     * @return array<int, string>
     */
    public function personLabels(array $matrics): array
    {
        if ($matrics === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            sprintf('SELECT Matric, Nom, Prenom FROM kp_licence WHERE Matric IN (%s)', implode(',', array_fill(0, count($matrics), '?'))),
            $matrics,
        );
        $labels = [];
        foreach ($rows as $row) {
            $labels[(int) $row['Matric']] = LegacyGamedayColumns::personLabel((int) $row['Matric'], $row['Nom'], $row['Prenom']);
        }

        return $labels;
    }

    /** @param array<string, string|null> $columns */
    public function updateGamedayColumns(int $gamedayId, array $columns): void
    {
        if ($columns !== []) {
            $this->connection->update('kp_journee', $columns, ['Id' => $gamedayId]);
        }
    }

    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed
    {
        return $this->connection->transactional(static fn (): mixed => $operation());
    }

    public function insert(Designation $designation, string $createdBy): int
    {
        $this->connection->insert('kp_designation', [
            'saison' => $designation->season,
            'role' => $designation->role,
            'perimetre_type' => $designation->scopeType->value,
            'perimetre_id' => $designation->scopeId,
            'matric' => $designation->matric,
            'club' => $designation->club,
            'autorite' => $designation->authority->value,
            'ordre' => $designation->order,
            'qualite' => $designation->quality,
            'created_by' => $createdBy,
        ]);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, mixed> $columns colonnes de kp_designation à modifier */
    public function update(int $id, array $columns): void
    {
        if ($columns !== []) {
            $this->connection->update('kp_designation', $columns, ['id' => $id]);
        }
    }

    public function delete(int $id): void
    {
        $this->connection->delete('kp_designation', ['id' => $id]);
    }

    /** @param array<string, mixed> $row */
    public static function hydrate(array $row): Designation
    {
        return new Designation(
            (int) $row['id'],
            $row['saison'],
            $row['role'],
            ScopeType::from($row['perimetre_type']),
            (string) $row['perimetre_id'],
            (int) $row['matric'],
            $row['club'],
            Authority::from($row['autorite']),
            (int) $row['ordre'],
            $row['qualite'],
        );
    }
}
