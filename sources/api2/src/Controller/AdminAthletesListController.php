<?php

namespace App\Controller;

use App\AgeCategory\AgeCategoryRule;
use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Paginated list of athletes, to find the players whose birth date is still to be completed
 * (CATEGORIE_AGE.md stories 28-30, PAGE_ATHLETES.md § 2.6 and § 6.8).
 */
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: '28. App4 - Athletes')]
final class AdminAthletesListController extends AbstractController
{
    /** « Joueur international » (GLOSSARY.md): no FFCK licence, i.e. a licence number from the non-federal range. */
    public const INTERNATIONAL_MATRIC_FROM = 2000000;

    private const DEFAULT_LIMIT = 50;
    private const MAX_LIMIT = 200;
    private const MIN_SEARCH_LENGTH = 2;

    public function __construct(private readonly Connection $connection)
    {
    }

    #[Route('/admin/athletes', name: 'admin_athletes_list', methods: ['GET'])]
    #[OA\Get(path: '/admin/athletes', summary: 'Paginated list of athletes with combinable filters and the total', tags: ['28. App4 - Athletes'])]
    #[OA\Parameter(name: 'q', in: 'query', required: false, description: 'Name, first name or licence number (min 2 chars)', schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'unknown_birthdate', in: 'query', required: false, description: '1 = birth date unknown (NULL or a conventional 1901-01-01 / 0000-00-00 date)', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'international', in: 'query', required: false, description: '1 = international player (Matric >= 2000000)', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'engaged_this_season', in: 'query', required: false, description: '1 = in a composition of a team of the active season', schema: new OA\Schema(type: 'boolean'))]
    #[OA\Parameter(name: 'page', in: 'query', required: false, description: 'Page number (default 1)', schema: new OA\Schema(type: 'integer'))]
    #[OA\Parameter(name: 'limit', in: 'query', required: false, description: 'Page size (default 50, max 200)', schema: new OA\Schema(type: 'integer'))]
    #[OA\Response(response: 200, description: '{items, total, page, limit}')]
    public function list(Request $request): JsonResponse
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = min(self::MAX_LIMIT, max(1, $request->query->getInt('limit', self::DEFAULT_LIMIT)));
        [$where, $params] = $this->filters($request);
        $whereClause = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $total = (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_licence l $whereClause", $params);
        // LIMIT/OFFSET cannot be bound on MariaDB with DBAL: integers cast above, interpolated here.
        $rows = $this->connection->fetchAllAssociative(
            "SELECT l.Matric, l.Nom, l.Prenom, l.Sexe, l.Naissance, l.Numero_club, c.Libelle AS clubLibelle
             FROM kp_licence l
             LEFT JOIN kp_club c ON c.Code = l.Numero_club
             $whereClause
             ORDER BY l.Nom, l.Prenom, l.Matric
             LIMIT $limit OFFSET " . (($page - 1) * $limit),
            $params
        );

        return $this->json([
            'items' => array_map(fn (array $row) => $this->formatAthlete($row), $rows),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
        ]);
    }

    /** @return array{0: list<string>, 1: list<mixed>} */
    private function filters(Request $request): array
    {
        $where = [];
        $params = [];

        $q = trim($request->query->getString('q'));
        if (mb_strlen($q) >= self::MIN_SEARCH_LENGTH) {
            if (ctype_digit($q)) {
                $where[] = '(l.Matric = ? OR l.Reserve = ?)';
                array_push($params, (int) $q, (int) $q);
            } else {
                $where[] = "(l.Nom LIKE ? OR l.Prenom LIKE ? OR CONCAT(l.Nom, ' ', l.Prenom) LIKE ? OR CONCAT(l.Prenom, ' ', l.Nom) LIKE ?)";
                array_push($params, ...array_fill(0, 4, "%$q%"));
            }
        }

        if ($request->query->getBoolean('unknown_birthdate')) {
            $placeholders = implode(', ', array_fill(0, count(AgeCategoryRule::CONVENTIONAL_BIRTH_DATES), '?'));
            $where[] = "(l.Naissance IS NULL OR l.Naissance IN ($placeholders))";
            array_push($params, ...AgeCategoryRule::CONVENTIONAL_BIRTH_DATES);
        }

        if ($request->query->getBoolean('international')) {
            $where[] = 'l.Matric >= ?';
            $params[] = self::INTERNATIONAL_MATRIC_FROM;
        }

        if ($request->query->getBoolean('engaged_this_season')) {
            $where[] = "EXISTS (SELECT 1 FROM kp_competition_equipe_joueur cej
                                JOIN kp_competition_equipe ce ON ce.Id = cej.Id_equipe
                                JOIN kp_saison s ON s.Code = ce.Code_saison AND s.Etat = 'A'
                                WHERE cej.Matric = l.Matric)";
        }

        return [$where, $params];
    }

    /** @param array<string, mixed> $row */
    private function formatAthlete(array $row): array
    {
        $birthDate = $row['Naissance'] === null ? null : (string) $row['Naissance'];

        return [
            'matric' => (int) $row['Matric'],
            'nom' => $row['Nom'],
            'prenom' => $row['Prenom'],
            'sexe' => $row['Sexe'],
            'naissance' => AgeCategoryRule::isUnknownBirthDate($birthDate) ? null : $birthDate,
            'international' => (int) $row['Matric'] >= self::INTERNATIONAL_MATRIC_FROM,
            'club' => $row['clubLibelle'] ?? '',
            'codeClub' => $row['Numero_club'] ?? '',
        ];
    }
}
