<?php

namespace App\Controller;

use App\Http\UnicodeJsonResponse;
use App\PublicResults\PublicResultsService;
use App\PublicResults\ResultsFormat;
use App\PublicResults\Scope\GroupScope;
use App\PublicSite\GroupSections;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class GroupController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private readonly PublicResultsService $results
    ) {
    }

    #[Route('/groups/{season}', name: 'groups', methods: ['GET'])]
    #[OA\Get(
        path: '/groups/{season}',
        summary: 'Get competition groups for a season',
        description: 'Returns groups with public competitions organized by section (for optgroup display)',
        tags: ['2. App2 - Public'],
        parameters: [
            new OA\Parameter(
                name: 'season',
                in: 'path',
                required: true,
                description: 'Season code (year, e.g. 2026)',
                schema: new OA\Schema(type: 'string', example: '2026')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns groups organized by section',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'season', type: 'string', example: '2026'),
                        new OA\Property(
                            property: 'sections',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'section', type: 'integer', example: 2),
                                    new OA\Property(property: 'label', type: 'string', example: 'Competitions_Nationales'),
                                    new OA\Property(
                                        property: 'groups',
                                        type: 'array',
                                        items: new OA\Items(
                                            properties: [
                                                new OA\Property(property: 'code', type: 'string', example: 'N1H'),
                                                new OA\Property(property: 'libelle', type: 'string', example: 'Nationale 1 Hommes'),
                                                new OA\Property(property: 'libelle_en', type: 'string', nullable: true, example: 'National 1 Men')
                                            ]
                                        )
                                    )
                                ]
                            )
                        )
                    ]
                )
            )
        ]
    )]
    public function getGroups(string $season): JsonResponse
    {
        $conn = $this->entityManager->getConnection();

        $sql = "SELECT g.Groupe as code, g.Libelle as libelle, g.Libelle_en as libelle_en, g.section, g.ordre
            FROM kp_groupe g
            WHERE g.section < " . GroupSections::PUBLIC_MAX . "
            AND EXISTS (
                SELECT 1 FROM kp_competition c
                WHERE c.Code_ref = g.Groupe
                AND c.Publication = 'O'
                AND c.Code_saison = ?
            )
            ORDER BY g.section, g.ordre";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $season);
        $result = $stmt->executeQuery();
        $rows = $result->fetchAllAssociative();

        $response = new JsonResponse([
            'season' => $season,
            'sections' => GroupSections::organize($rows)
        ]);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);
        return $response;
    }

    #[Route('/group/{season}/{groupCode}/games', name: 'group_games', methods: ['GET'])]
    #[OA\Get(
        path: '/group/{season}/{groupCode}/games',
        summary: 'Get games for a competition group',
        description: 'Returns all games from competitions in the specified group for the given season',
        tags: ['2. App2 - Public'],
        parameters: [
            new OA\Parameter(
                name: 'season',
                in: 'path',
                required: true,
                description: 'Season code (year)',
                schema: new OA\Schema(type: 'string', example: '2026')
            ),
            new OA\Parameter(
                name: 'groupCode',
                in: 'path',
                required: true,
                description: 'Group code (e.g. N1H, N1F)',
                schema: new OA\Schema(type: 'string', example: 'N1H')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns list of games for the group',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'g_id', type: 'integer', example: 456),
                            new OA\Property(property: 'c_code', type: 'string', example: 'N1H'),
                            new OA\Property(property: 'd_phase', type: 'string', example: 'Poules'),
                            new OA\Property(property: 't_a_label', type: 'string', example: 'Team A'),
                            new OA\Property(property: 't_b_label', type: 'string', example: 'Team B'),
                            new OA\Property(property: 'g_score_a', type: 'integer', nullable: true, example: 5),
                            new OA\Property(property: 'g_score_b', type: 'integer', nullable: true, example: 3),
                            new OA\Property(property: 'g_date', type: 'string', example: '2025-01-15'),
                            new OA\Property(property: 'g_time', type: 'string', example: '10:00:00'),
                            new OA\Property(property: 'c_order', type: 'integer', nullable: true, example: 1, description: 'Competition order within the group'),
                            new OA\Property(property: 'c_type', type: 'string', nullable: true, example: 'CHPT', description: 'Competition type (CHPT or CP)')
                        ]
                    )
                )
            )
        ]
    )]
    public function getGroupGames(string $season, string $groupCode): JsonResponse
    {
        return new UnicodeJsonResponse(
            $this->results->games(new GroupScope($season, $groupCode), ResultsFormat::Group)
        );
    }

    #[Route('/group/{season}/{groupCode}/charts', name: 'group_charts', methods: ['GET'])]
    #[OA\Get(
        path: '/group/{season}/{groupCode}/charts',
        summary: 'Get rankings and brackets for a competition group',
        description: 'Returns tournament structure with pools, brackets, rankings for all competitions in the group',
        tags: ['2. App2 - Public'],
        parameters: [
            new OA\Parameter(
                name: 'season',
                in: 'path',
                required: true,
                description: 'Season code (year)',
                schema: new OA\Schema(type: 'string', example: '2026')
            ),
            new OA\Parameter(
                name: 'groupCode',
                in: 'path',
                required: true,
                description: 'Group code (e.g. N1H, N1F)',
                schema: new OA\Schema(type: 'string', example: 'N1H')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns tournament charts and rankings',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'code', type: 'string', example: 'N1H'),
                            new OA\Property(property: 'libelle', type: 'string', example: 'Nationale 1 Hommes'),
                            new OA\Property(property: 'type', type: 'string', example: 'CHPT'),
                            new OA\Property(property: 'rounds', type: 'object', description: 'Tournament rounds structure'),
                            new OA\Property(
                                property: 'ranking',
                                type: 'array',
                                items: new OA\Items(type: 'object'),
                                description: 'Team rankings'
                            )
                        ]
                    )
                )
            )
        ]
    )]
    public function getGroupCharts(string $season, string $groupCode): JsonResponse
    {
        return new UnicodeJsonResponse(
            $this->results->charts(new GroupScope($season, $groupCode), ResultsFormat::Group)
        );
    }

    #[Route('/group/{season}/{groupCode}/team/{teamId}/stats', name: 'group_team_stats', methods: ['GET'])]
    #[OA\Get(
        path: '/group/{season}/{groupCode}/team/{teamId}/stats',
        summary: 'Get team statistics per competition within a group',
        description: 'Returns player statistics for a team in each competition of the group (goals, cards, etc.), one entry per competition the team participates in. The team is identified by its kp_competition_equipe Id in any competition of the group; Code_club + Numero are used to find its entries in other competitions.',
        tags: ['2. App2 - Public'],
        parameters: [
            new OA\Parameter(
                name: 'season',
                in: 'path',
                required: true,
                description: 'Season code (year)',
                schema: new OA\Schema(type: 'string', example: '2026')
            ),
            new OA\Parameter(
                name: 'groupCode',
                in: 'path',
                required: true,
                description: 'Group code (e.g. N1H)',
                schema: new OA\Schema(type: 'string', example: 'N1H')
            ),
            new OA\Parameter(
                name: 'teamId',
                in: 'path',
                required: true,
                description: 'kp_competition_equipe.Id for the team in any competition of the group',
                schema: new OA\Schema(type: 'integer', example: 456)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns an array of competitions with player statistics',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'competition_code', type: 'string', example: 'N1H'),
                            new OA\Property(property: 'competition_label', type: 'string', example: 'Nationale 1 Hommes'),
                            new OA\Property(property: 'team_id', type: 'integer', example: 456),
                            new OA\Property(
                                property: 'players',
                                type: 'array',
                                items: new OA\Items(
                                    properties: [
                                        new OA\Property(property: 'licence', type: 'string', example: '123456'),
                                        new OA\Property(property: 'name', type: 'string', example: 'Dupont'),
                                        new OA\Property(property: 'firstname', type: 'string', example: 'Jean'),
                                        new OA\Property(property: 'number', type: 'integer', example: 5),
                                        new OA\Property(property: 'captain', type: 'string', example: 'C'),
                                        new OA\Property(property: 'goals', type: 'integer', example: 3),
                                        new OA\Property(property: 'green_cards', type: 'integer', example: 1),
                                        new OA\Property(property: 'yellow_cards', type: 'integer', example: 0),
                                        new OA\Property(property: 'red_cards', type: 'integer', example: 0),
                                        new OA\Property(property: 'exclusions', type: 'integer', example: 0)
                                    ]
                                )
                            )
                        ]
                    )
                )
            )
        ]
    )]
    public function getGroupTeamStats(string $season, string $groupCode, int $teamId): JsonResponse
    {
        $conn = $this->entityManager->getConnection();

        // Resolve Code_club + Numero for the given team entry
        $resolveStmt = $conn->prepare("SELECT Code_club, Numero FROM kp_competition_equipe WHERE Id = ?");
        $resolveStmt->bindValue(1, $teamId);
        $resolveResult = $resolveStmt->executeQuery();
        $teamRef = $resolveResult->fetchAssociative();

        if (!$teamRef) {
            return new JsonResponse([]);
        }

        // Find all team entries with the same Code_club + Numero in each competition of the group
        $teamsSql = "
            SELECT ce.Id AS team_id, c.Code AS competition_code, c.Soustitre2 AS competition_label
            FROM kp_competition_equipe ce
            INNER JOIN kp_competition c ON (ce.Code_compet = c.Code AND ce.Code_saison = c.Code_saison)
            WHERE c.Code_ref = ?
            AND c.Code_saison = ?
            AND c.Publication = 'O'
            AND ce.Code_club = ?
            AND ce.Numero = ?
            ORDER BY c.GroupOrder, c.Code
        ";

        $stmt = $conn->prepare($teamsSql);
        $stmt->bindValue(1, $groupCode);
        $stmt->bindValue(2, $season);
        $stmt->bindValue(3, $teamRef['Code_club']);
        $stmt->bindValue(4, $teamRef['Numero']);
        $result = $stmt->executeQuery();
        $teamEntries = $result->fetchAllAssociative();

        if (empty($teamEntries)) {
            return new JsonResponse([]);
        }

        $results = [];

        foreach ($teamEntries as $entry) {
            $teamId = (int) $entry['team_id'];

            $statsSql = "
                SELECT
                    l.Matric AS licence, l.Nom AS name, l.Prenom AS firstname,
                    l.Sexe AS gender, j.Numero AS number, j.Capitaine AS captain,
                    CASE WHEN j.Capitaine = 'E' THEN 0 ELSE SUM(IF(md.Id_evt_match = 'B', 1, 0)) END AS goals,
                    SUM(IF(md.Id_evt_match = 'V', 1, 0)) AS green_cards,
                    CASE WHEN j.Capitaine = 'E' THEN 0 ELSE SUM(IF(md.Id_evt_match = 'J', 1, 0)) END AS yellow_cards,
                    SUM(IF(md.Id_evt_match = 'R', 1, 0)) AS red_cards,
                    SUM(IF(md.Id_evt_match = 'D', 1, 0)) AS exclusions
                FROM kp_competition_equipe_joueur j
                JOIN kp_licence l ON (j.Matric = l.Matric)
                LEFT JOIN (
                    kp_match_detail md
                    JOIN kp_match m ON md.Id_match = m.Id
                    JOIN kp_journee jou ON m.Id_journee = jou.Id
                    JOIN kp_competition c ON (jou.Code_competition = c.Code AND jou.Code_saison = c.Code_saison)
                ) ON l.Matric = md.Competiteur AND c.Code_ref = ? AND c.Code_saison = ? AND c.Code = ?
                WHERE j.Id_equipe = ?
                  AND (j.Capitaine IS NULL OR j.Capitaine NOT IN ('A', 'X'))
                GROUP BY l.Matric, l.Nom, l.Prenom, l.Sexe, j.Numero, j.Capitaine
                ORDER BY CASE WHEN j.Capitaine = 'E' THEN 1 ELSE 0 END, j.Numero ASC
            ";

            $stmt2 = $conn->prepare($statsSql);
            $stmt2->bindValue(1, $groupCode);
            $stmt2->bindValue(2, $season);
            $stmt2->bindValue(3, $entry['competition_code']);
            $stmt2->bindValue(4, $teamId);
            $result2 = $stmt2->executeQuery();
            $players = $result2->fetchAllAssociative();

            $results[] = [
                'competition_code' => $entry['competition_code'],
                'competition_label' => $entry['competition_label'],
                'team_id' => $teamId,
                'players' => $players,
            ];
        }

        $response = new JsonResponse($results);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);
        return $response;
    }

    #[Route('/group/{season}/{groupCode}/teams', name: 'group_teams', methods: ['GET'])]
    #[OA\Get(
        path: '/group/{season}/{groupCode}/teams',
        summary: 'Get teams for a competition group',
        description: 'Returns all teams from competitions in the specified group (for scrutineering)',
        tags: ['2. App2 - Public'],
        parameters: [
            new OA\Parameter(
                name: 'season',
                in: 'path',
                required: true,
                description: 'Season code (year)',
                schema: new OA\Schema(type: 'string', example: '2026')
            ),
            new OA\Parameter(
                name: 'groupCode',
                in: 'path',
                required: true,
                description: 'Group code (e.g. N1H, N1F)',
                schema: new OA\Schema(type: 'string', example: 'N1H')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns list of teams for the group',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 't_id', type: 'integer', example: 123),
                            new OA\Property(property: 't_label', type: 'string', example: 'Team Name'),
                            new OA\Property(property: 't_club', type: 'string', example: 'CLUB01'),
                            new OA\Property(property: 't_logo', type: 'string', example: 'logo/team.png'),
                            new OA\Property(property: 'c_code', type: 'string', example: 'N1H'),
                            new OA\Property(property: 'c_category', type: 'string', example: 'Nationale 1 Hommes')
                        ]
                    )
                )
            )
        ]
    )]
    public function getGroupTeams(string $season, string $groupCode): JsonResponse
    {
        $conn = $this->entityManager->getConnection();

        $sql = "SELECT ce.Id team_id, ce.Libelle label, ce.Code_club club,
            CASE WHEN ce.logo IS NULL THEN 'KIP/logo/empty-logo.png' ELSE ce.logo END logo,
            c.Code c_code, c.Soustitre2 category
            FROM kp_competition_equipe ce
            INNER JOIN kp_competition c ON (ce.Code_compet = c.Code AND ce.Code_saison = c.Code_saison)
            WHERE c.Code_ref = ?
            AND c.Code_saison = ?
            AND c.Publication = 'O'
            ORDER BY c.Soustitre2, ce.Libelle";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $groupCode);
        $stmt->bindValue(2, $season);
        $result = $stmt->executeQuery();
        $teams = $result->fetchAllAssociative();

        $response = new JsonResponse($teams);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);
        return $response;
    }
}
