<?php

namespace App\Athlete;

use App\AgeCategory\AgeCategoryRule;
use App\Service\ActiveSeason;
use Doctrine\DBAL\Connection;

/**
 * Paginated athletes list, to find the players whose birth date is still to be completed (CATEGORIE_AGE.md
 * stories 28-30, PAGE_ATHLETES.md § 6.8): the filters are combined with AND, sorted by name, with the total.
 */
final class AthletesListRepository
{
    private const MIN_SEARCH_LENGTH = 2;

    public function __construct(
        private readonly Connection $connection,
        private readonly ActiveSeason $activeSeason,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function page(AthletesListFilters $filters, int $page, int $limit): array
    {
        [$where, $params] = $this->conditions($filters);
        $whereClause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $total = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_licence l $whereClause", $params);
        // LIMIT/OFFSET cannot be bound on MariaDB with DBAL: integers, interpolated here.
        $rows = $this->connection->fetchAllAssociative(
            "SELECT l.Matric, l.Nom, l.Prenom, l.Sexe, l.Naissance, l.Numero_club, c.Libelle AS clubLibelle
             FROM kp_licence l
             LEFT JOIN kp_club c ON c.Code = l.Numero_club
             $whereClause
             ORDER BY l.Nom, l.Prenom, l.Matric
             LIMIT $limit OFFSET " . (($page - 1) * $limit),
            $params
        );

        return ['items' => array_map(self::athlete(...), $rows), 'total' => $total];
    }

    /** @return array{0: list<string>, 1: list<mixed>} */
    private function conditions(AthletesListFilters $filters): array
    {
        $where = [];
        $params = [];

        $q = trim($filters->search);
        if (mb_strlen($q) >= self::MIN_SEARCH_LENGTH) {
            if (ctype_digit($q)) {
                $where[] = '(l.Matric = ? OR l.Reserve = ?)';
                array_push($params, (int) $q, (int) $q);
            } else {
                $where[] = "(l.Nom LIKE ? OR l.Prenom LIKE ? OR CONCAT(l.Nom, ' ', l.Prenom) LIKE ? OR CONCAT(l.Prenom, ' ', l.Nom) LIKE ?)";
                array_push($params, ...array_fill(0, 4, "%$q%"));
            }
        }

        if ($filters->unknownBirthDate) {
            $placeholders = implode(', ', array_fill(0, count(AgeCategoryRule::CONVENTIONAL_BIRTH_DATES), '?'));
            $where[] = "(l.Naissance IS NULL OR l.Naissance IN ($placeholders))";
            array_push($params, ...AgeCategoryRule::CONVENTIONAL_BIRTH_DATES);
        }

        if ($filters->international) {
            $where[] = 'l.Matric >= ?';
            $params[] = InternationalPlayer::FIRST_MATRIC;
        }

        if ($filters->engagedThisSeason) {
            $where[] = 'EXISTS (SELECT 1 FROM kp_competition_equipe_joueur cej
                                JOIN kp_competition_equipe ce ON ce.Id = cej.Id_equipe
                                WHERE cej.Matric = l.Matric AND ce.Code_saison = ?)';
            $params[] = $this->activeSeason->code();
        }

        return [$where, $params];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private static function athlete(array $row): array
    {
        $birthDate = $row['Naissance'] === null ? null : (string) $row['Naissance'];

        return [
            'matric' => (int) $row['Matric'],
            'nom' => $row['Nom'],
            'prenom' => $row['Prenom'],
            'sexe' => $row['Sexe'],
            'naissance' => AgeCategoryRule::isUnknownBirthDate($birthDate) ? null : $birthDate,
            'international' => InternationalPlayer::is((int) $row['Matric']),
            'club' => $row['clubLibelle'] ?? '',
            'codeClub' => $row['Numero_club'] ?? '',
        ];
    }
}
