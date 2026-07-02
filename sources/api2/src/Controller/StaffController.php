<?php

namespace App\Controller;

use App\Service\TokenAuthService;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/staff', name: 'staff_')]
class StaffController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TokenAuthService $tokenAuthService
    ) {
    }

    #[Route('/test', name: 'test', methods: ['GET'])]
    #[OA\Get(
        path: '/staff/test',
        summary: 'Test endpoint for staff authentication',
        tags: ['3. App2 - Staff'],
        security: [['ApiToken' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Test successful',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'result', type: 'string', example: 'OK'),
                        new OA\Property(property: 'user', type: 'string', example: '123456')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized - Invalid or missing token')
        ]
    )]
    public function test(Request $request): JsonResponse
    {
        try {
            $auth = $this->tokenAuthService->validateToken($request, null, null, 3);
        } catch (\RuntimeException) {
            return $this->tokenAuthService->createForbiddenResponse();
        }
        if (!$auth) {
            return $this->tokenAuthService->createUnauthorizedResponse();
        }

        return new JsonResponse([
            'result' => 'OK',
            'user' => $auth['user']
        ]);
    }

    #[Route('/{eventId}/teams', name: 'teams', methods: ['GET'])]
    #[OA\Get(
        path: '/staff/{eventId}/teams',
        summary: 'Get teams for scrutineering',
        tags: ['3. App2 - Staff'],
        security: [['ApiToken' => []]],
        parameters: [
            new OA\Parameter(
                name: 'eventId',
                in: 'path',
                required: true,
                description: 'Event ID',
                schema: new OA\Schema(type: 'integer', example: 123)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns list of teams',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'team_id', type: 'integer', example: 456),
                            new OA\Property(property: 'label', type: 'string', example: 'Team A'),
                            new OA\Property(property: 'club', type: 'string', example: 'CLUB01'),
                            new OA\Property(property: 'logo', type: 'string', nullable: true)
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized - Invalid or missing token'),
            new OA\Response(response: 403, description: 'Forbidden - No access to this event')
        ]
    )]
    public function getTeams(Request $request, int $eventId): JsonResponse
    {
        try {
            $auth = $this->tokenAuthService->validateToken($request, null, $eventId, 3);
        } catch (\RuntimeException) {
            return $this->tokenAuthService->createForbiddenResponse();
        }
        if (!$auth) {
            return $this->tokenAuthService->createUnauthorizedResponse();
        }

        $conn = $this->entityManager->getConnection();

        $sql = "SELECT ce.Id team_id, ce.Libelle label, ce.Code_club club, ce.logo
            FROM kp_competition_equipe ce
            INNER JOIN kp_journee j ON (ce.Code_compet = j.Code_competition AND ce.Code_saison = j.Code_saison)
            INNER JOIN kp_evenement_journee ej ON (j.Id = ej.Id_journee)
            WHERE ej.Id_evenement = ?
            GROUP BY team_id
            ORDER BY club, label";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $eventId);
        $result = $stmt->executeQuery();
        $teams = $result->fetchAllAssociative();

        $response = new JsonResponse($teams);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);
        return $response;
    }

    #[Route('/{eventId}/team/{teamId}/players', name: 'team_players', methods: ['GET'])]
    #[OA\Get(
        path: '/staff/{eventId}/team/{teamId}/players',
        summary: 'Get players for a team',
        description: 'Get list of players with scrutineering data.',
        tags: ['3. App2 - Staff'],
        security: [['ApiToken' => []]],
        parameters: [
            new OA\Parameter(
                name: 'eventId',
                in: 'path',
                required: true,
                description: 'Event ID',
                schema: new OA\Schema(type: 'integer', example: 222)
            ),
            new OA\Parameter(
                name: 'teamId',
                in: 'path',
                required: true,
                description: 'Team ID',
                schema: new OA\Schema(type: 'integer', example: 456)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns list of players with scrutineering data (fresh data)',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'integer'),
                            new OA\Property(property: 'licence', type: 'string'),
                            new OA\Property(property: 'name', type: 'string'),
                            new OA\Property(property: 'number', type: 'integer'),
                            new OA\Property(property: 'kayak_status', type: 'string', nullable: true),
                            new OA\Property(property: 'vest_status', type: 'string', nullable: true),
                            new OA\Property(property: 'helmet_status', type: 'string', nullable: true),
                            new OA\Property(property: 'paddle_count', type: 'integer', nullable: true)
                        ]
                    )
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized - Invalid or missing token')
        ]
    )]
    public function getPlayers(Request $request, int $eventId, int $teamId): JsonResponse
    {
        try {
            $auth = $this->tokenAuthService->validateToken($request, null, $eventId, 3);
        } catch (\RuntimeException) {
            return $this->tokenAuthService->createForbiddenResponse();
        }
        if (!$auth) {
            return $this->tokenAuthService->createUnauthorizedResponse();
        }

        // Ensure the team belongs to the authenticated event (IDOR protection).
        if (!$this->getTeamCompositionInfo($teamId, $eventId)) {
            return new JsonResponse(['error' => 'Team not found'], 404);
        }

        $conn = $this->entityManager->getConnection();

        // Return every player of the composition (including referees 'A' and inactive 'X'):
        // the equipment-control mode filters those out client-side, while the composition
        // mode needs them all so the scrutineer can change any player's status.
        $sql = "SELECT cej.Matric player_id, cej.Nom last_name, cej.Prenom first_name,
            cej.Sexe gender, cej.Numero num, cej.Capitaine cap, l.Numero_club club_code,
            sc.kayak_status, sc.kayak_print, sc.vest_status, sc.vest_print, sc.helmet_status,
            sc.helmet_print, sc.paddle_count, sc.paddle_print, sc.comment
            FROM kp_competition_equipe_joueur cej
            LEFT OUTER JOIN kp_licence l ON cej.Matric = l.Matric
            LEFT OUTER JOIN kp_scrutineering sc ON (cej.Id_equipe = sc.id_equipe AND cej.Matric = sc.matric)
            WHERE cej.Id_equipe = ?
            ORDER BY FIELD(IF(cej.Capitaine='C', '-', IF(cej.Capitaine='', '-', cej.Capitaine)), '-', 'E', 'A', 'X'), num, last_name, first_name";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $teamId);
        $result = $stmt->executeQuery();
        $players = $result->fetchAllAssociative();

        $response = new JsonResponse($players);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);

        // If force parameter is present, add no-cache headers
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    #[Route('/{eventId}/team/{teamId}/player/{playerId}/{parameter}/{value}', name: 'update_player', methods: ['PUT'], defaults: ['value' => null])]
    #[OA\Put(
        path: '/staff/{eventId}/team/{teamId}/player/{playerId}/{parameter}/{value}',
        summary: 'Update player scrutineering data',
        tags: ['3. App2 - Staff'],
        security: [['ApiToken' => []]],
        parameters: [
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 222)),
            new OA\Parameter(name: 'playerId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'parameter', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['kayak_status', 'vest_status', 'helmet_status', 'paddle_count'])),
            new OA\Parameter(name: 'value', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Player data updated'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 405, description: 'Invalid parameter')
        ]
    )]
    #[Route('/{eventId}/team/{teamId}/player/{playerId}/comment', name: 'update_player_comment', methods: ['PUT'])]
    #[OA\Put(
        path: '/staff/{eventId}/team/{teamId}/player/{playerId}/comment',
        summary: 'Update player comment',
        tags: ['3. App2 - Staff'],
        security: [['ApiToken' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'comment', type: 'string', example: 'Equipment OK', maxLength: 255)
                ]
            )
        ),
        parameters: [
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 222)),
            new OA\Parameter(name: 'playerId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Comment updated',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'comment', type: 'string', example: 'Equipment OK')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function updatePlayer(Request $request, int $eventId, int $playerId, int $teamId, string $parameter, ?int $value = null): JsonResponse
    {
        try {
            $auth = $this->tokenAuthService->validateToken($request, null, $eventId, 3);
        } catch (\RuntimeException) {
            return $this->tokenAuthService->createForbiddenResponse();
        }
        if (!$auth) {
            return $this->tokenAuthService->createUnauthorizedResponse();
        }

        // Ensure the team belongs to the authenticated event (IDOR protection).
        if (!$this->getTeamCompositionInfo($teamId, $eventId)) {
            return new JsonResponse(['error' => 'Team not found'], 404);
        }

        $conn = $this->entityManager->getConnection();

        if ($parameter === 'comment') {
            $input = json_decode($request->getContent(), true);
            $comment = isset($input['comment']) ? htmlspecialchars(substr($input['comment'], 0, 255), ENT_QUOTES, 'UTF-8') : '';

            $sql = "INSERT INTO kp_scrutineering (id_equipe, matric, comment)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE comment = ?";

            $conn->executeStatement($sql, [$teamId, $playerId, $comment, $comment]);

            try {
                $conn->executeStatement(
                    "INSERT INTO kp_journal (Dates, Users, Actions, Evenements, Journal) VALUES (NOW(), ?, 'Scrut commentaire', ?, ?)",
                    [$auth['user'], $eventId, "Equipe $teamId - Joueur $playerId"]
                );
            } catch (\Exception) {
            }

            return new JsonResponse(['comment' => $comment]);
        }

        if (!in_array($parameter, ['kayak_status', 'vest_status', 'helmet_status', 'paddle_count'])) {
            return new JsonResponse(['error' => 'Invalid parameter'], 405);
        }

        if ($value === null) {
            return new JsonResponse(['error' => 'Value is required'], 405);
        }

        $sql = "INSERT INTO kp_scrutineering (id_equipe, matric, $parameter)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE $parameter = ?";

        try {
            $conn->executeStatement($sql, [$teamId, $playerId, $value, $value]);

            $paramLabels = [
                'kayak_status'  => 'kayak',
                'vest_status'   => 'gilet',
                'helmet_status' => 'casque',
                'paddle_count'  => 'pagaies',
            ];
            try {
                $conn->executeStatement(
                    "INSERT INTO kp_journal (Dates, Users, Actions, Evenements, Journal) VALUES (NOW(), ?, 'Scrut équipement', ?, ?)",
                    [$auth['user'], $eventId, "Equipe $teamId - Joueur $playerId - " . ($paramLabels[$parameter] ?? $parameter) . "=$value"]
                );
            } catch (\Exception) {
            }

            return new JsonResponse(['value' => $value]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 401);
        }
    }

    #[Route('/{eventId}/team/{teamId}/reset', name: 'reset_team', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/staff/{eventId}/team/{teamId}/reset',
        summary: 'Reset all scrutineering data for a team',
        description: 'Deletes every kp_scrutineering row for the team (equipment statuses, paddle counts and comments).',
        tags: ['3. App2 - Staff'],
        security: [['ApiToken' => []]],
        parameters: [
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'integer', example: 222)),
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Team scrutineering reset'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function resetTeam(Request $request, int $eventId, int $teamId): JsonResponse
    {
        try {
            $auth = $this->tokenAuthService->validateToken($request, null, $eventId, 3);
        } catch (\RuntimeException) {
            return $this->tokenAuthService->createForbiddenResponse();
        }
        if (!$auth) {
            return $this->tokenAuthService->createUnauthorizedResponse();
        }

        // Ensure the team belongs to the authenticated event (IDOR protection).
        if (!$this->getTeamCompositionInfo($teamId, $eventId)) {
            return new JsonResponse(['error' => 'Team not found'], 404);
        }

        $conn = $this->entityManager->getConnection();

        try {
            $deleted = $conn->executeStatement(
                "DELETE FROM kp_scrutineering WHERE id_equipe = ?",
                [$teamId]
            );

            try {
                $conn->executeStatement(
                    "INSERT INTO kp_journal (Dates, Users, Actions, Evenements, Journal) VALUES (NOW(), ?, 'Scrut réinitialisation', ?, ?)",
                    [$auth['user'], $eventId, "Equipe $teamId - réinitialisation complète"]
                );
            } catch (\Exception) {
            }

            return new JsonResponse(['reset' => true, 'deleted' => $deleted]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 401);
        }
    }

    #[Route('/{eventId}/overview', name: 'overview', methods: ['GET'])]
    #[OA\Get(
        path: '/staff/{eventId}/overview',
        summary: 'Get scrutineering overview for all teams in an event',
        description: 'Returns all teams grouped by category (Soustitre2) with their scrutineering status: none/partial/complete.',
        tags: ['3. App2 - Staff'],
        security: [['ApiToken' => []]],
        parameters: [
            new OA\Parameter(
                name: 'eventId',
                in: 'path',
                required: true,
                description: 'Event ID',
                schema: new OA\Schema(type: 'integer', example: 123)
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Returns overview data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'categories',
                            type: 'array',
                            items: new OA\Items(type: 'string', example: 'Elite H')
                        ),
                        new OA\Property(
                            property: 'clubs',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'code', type: 'string', example: 'CAEN'),
                                    new OA\Property(property: 'label', type: 'string', example: 'Caen Canoe Polo')
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'teams',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'team_id', type: 'integer'),
                                    new OA\Property(property: 'label', type: 'string'),
                                    new OA\Property(property: 'club', type: 'string'),
                                    new OA\Property(property: 'logo', type: 'string', nullable: true),
                                    new OA\Property(property: 'category', type: 'string'),
                                    new OA\Property(property: 'status', type: 'string', enum: ['none', 'partial', 'complete'])
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden - No access to this event')
        ]
    )]
    public function getOverview(Request $request, int $eventId): JsonResponse
    {
        try {
            $auth = $this->tokenAuthService->validateToken($request, null, $eventId, 3);
        } catch (\RuntimeException) {
            return $this->tokenAuthService->createForbiddenResponse();
        }
        if (!$auth) {
            return $this->tokenAuthService->createUnauthorizedResponse();
        }

        $conn = $this->entityManager->getConnection();

        // For each team in the event, compute scrutineering status by joining players and scrutineering data.
        // A player counts as "complete" if kayak_status=1 AND vest_status=1 AND helmet_status=1 AND paddle_count>0.
        // A player counts as "checked" if at least one equipment is > 0. A bare kp_scrutineering row with
        // everything reset to 0/null (e.g. validated then cancelled) must NOT make the team "partial".
        // Coaches (Capitaine='E') and absent/excluded players are excluded from the check.
        $sql = "SELECT
                ce.Id                                       AS team_id,
                ce.Libelle                                  AS label,
                ce.Code_club                                AS club_code,
                COALESCE(cl.Libelle, ce.Code_club, '')      AS club_label,
                ce.logo                                     AS logo,
                COALESCE(c.Soustitre2, c.Libelle, '')       AS category,
                COUNT(DISTINCT cej.Matric)                  AS total_players,
                COUNT(DISTINCT CASE WHEN COALESCE(sc.kayak_status, 0) > 0
                          OR COALESCE(sc.vest_status, 0)   > 0
                          OR COALESCE(sc.helmet_status, 0) > 0
                          OR COALESCE(sc.paddle_count, 0)  > 0 THEN sc.matric END)
                                                            AS checked_players,
                COUNT(DISTINCT CASE WHEN sc.kayak_status = 1
                          AND sc.vest_status  = 1
                          AND sc.helmet_status= 1
                          AND sc.paddle_count > 0 THEN sc.matric END)
                                                            AS complete_players
            FROM kp_competition_equipe ce
            INNER JOIN kp_competition c
                ON (ce.Code_compet = c.Code AND ce.Code_saison = c.Code_saison)
            INNER JOIN kp_journee j
                ON (ce.Code_compet = j.Code_competition AND ce.Code_saison = j.Code_saison)
            INNER JOIN kp_evenement_journee ej
                ON (j.Id = ej.Id_journee)
            LEFT JOIN kp_club cl
                ON ce.Code_club = cl.Code
            LEFT JOIN kp_competition_equipe_joueur cej
                ON (cej.Id_equipe = ce.Id AND cej.Capitaine NOT IN ('E','A','X'))
            LEFT JOIN kp_scrutineering sc
                ON (sc.id_equipe = ce.Id AND sc.matric = cej.Matric)
            WHERE ej.Id_evenement = ?
            GROUP BY ce.Id, ce.Libelle, ce.Code_club, cl.Libelle, ce.logo, category
            ORDER BY category, club_label, label";

        $stmt = $conn->prepare($sql);
        $stmt->bindValue(1, $eventId);
        $result = $stmt->executeQuery();
        $rows = $result->fetchAllAssociative();

        $teams = [];
        $categoriesSet = [];
        $clubsMap = []; // code → {code, label, logo}

        foreach ($rows as $row) {
            $total    = (int) $row['total_players'];
            $checked  = (int) $row['checked_players'];
            $complete = (int) $row['complete_players'];

            if ($checked === 0) {
                $status = 'none';
            } elseif ($total > 0 && $complete === $total) {
                $status = 'complete';
            } else {
                $status = 'partial';
            }

            $category  = $row['category']   ?: '—';
            $clubCode  = $row['club_code']   ?: '—';
            $clubLabel = $row['club_label']  ?: $clubCode;

            $categoriesSet[$category] = true;

            if (!isset($clubsMap[$clubCode])) {
                $clubsMap[$clubCode] = [
                    'code'  => $clubCode,
                    'label' => $clubLabel,
                ];
            }

            $teams[] = [
                'team_id'   => (int) $row['team_id'],
                'label'     => $row['label'],
                'club'      => $clubCode,
                'logo'      => $row['logo'],
                'category'  => $category,
                'status'    => $status,
            ];
        }

        // Sort clubs by label
        $clubs = array_values($clubsMap);
        usort($clubs, fn($a, $b) => strcmp($a['label'], $b['label']));

        $response = new JsonResponse([
            'categories' => array_keys($categoriesSet),
            'clubs'      => $clubs,
            'teams'      => $teams,
        ]);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);
        return $response;
    }

    // -----------------------------------------------------------------------
    // Composition adjustment (kp_competition_equipe_joueur)
    //
    // These endpoints let a scrutineer (profile <= 3, event token) adjust the
    // actual team composition (player number, status, adding a player), not just
    // the equipment-scrutineering data. They are blocked when the competition is
    // locked (kp_competition.Verrou = 'O').
    // -----------------------------------------------------------------------

    /**
     * Team info (season/competition/lock) for a composition team that belongs to the
     * given event, or null if the team is unknown OR not part of that event.
     *
     * The event scoping is essential: the token is validated against $eventId, so we
     * must reject a teamId that belongs to a different event (IDOR protection).
     */
    private function getTeamCompositionInfo(int $teamId, int $eventId): ?array
    {
        $conn = $this->entityManager->getConnection();
        $row = $conn->fetchAssociative(
            "SELECT ce.Code_compet, ce.Code_saison, comp.Verrou
             FROM kp_competition_equipe ce
             INNER JOIN kp_journee j
                 ON (ce.Code_compet = j.Code_competition AND ce.Code_saison = j.Code_saison)
             INNER JOIN kp_evenement_journee ej
                 ON (j.Id = ej.Id_journee)
             LEFT JOIN kp_competition comp
                 ON (ce.Code_compet = comp.Code AND ce.Code_saison = comp.Code_saison)
             WHERE ce.Id = ? AND ej.Id_evenement = ?
             LIMIT 1",
            [$teamId, $eventId]
        );
        if (!$row) {
            return null;
        }
        return [
            'code_compet' => $row['Code_compet'],
            'code_saison' => (string) $row['Code_saison'],
            'locked'      => $row['Verrou'] === 'O',
        ];
    }

    /** Compute a player's category id from birth date and season (same logic as admin presence). */
    private function computeCategory(?string $birthDate, string $season): string
    {
        if (!$birthDate) {
            return '';
        }
        $age = (int) $season - (int) substr($birthDate, 0, 4);
        $row = $this->entityManager->getConnection()->fetchAssociative(
            "SELECT id FROM kp_categorie WHERE age_min <= ? AND age_max >= ? LIMIT 1",
            [$age, $age]
        );
        return $row ? (string) $row['id'] : '';
    }

    #[Route('/{eventId}/team/{teamId}/composition/{playerId}', name: 'update_composition', methods: ['PUT'])]
    #[OA\Put(
        path: '/staff/{eventId}/team/{teamId}/composition/{playerId}',
        summary: 'Adjust a player composition (number / status)',
        description: 'Updates the player number and/or status (Capitaine) in the real team composition. Blocked if the competition is locked.',
        tags: ['3. App2 - Staff'],
        security: [['ApiToken' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'numero', type: 'integer', nullable: true, example: 12),
                    new OA\Property(property: 'capitaine', type: 'string', nullable: true, enum: ['-', 'C', 'E', 'A', 'X'])
                ]
            )
        ),
        parameters: [
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'playerId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Composition updated'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Competition locked'),
            new OA\Response(response: 405, description: 'Invalid parameter')
        ]
    )]
    public function updateComposition(Request $request, int $eventId, int $teamId, int $playerId): JsonResponse
    {
        try {
            $auth = $this->tokenAuthService->validateToken($request, null, $eventId, 3);
        } catch (\RuntimeException) {
            return $this->tokenAuthService->createForbiddenResponse();
        }
        if (!$auth) {
            return $this->tokenAuthService->createUnauthorizedResponse();
        }

        $info = $this->getTeamCompositionInfo($teamId, $eventId);
        if (!$info) {
            return new JsonResponse(['error' => 'Team not found'], 404);
        }
        if ($info['locked']) {
            return new JsonResponse(['error' => 'Competition is locked'], 403);
        }

        $input = json_decode($request->getContent(), true) ?: [];
        $set = [];
        $params = [];

        if (array_key_exists('numero', $input)) {
            $set[] = 'Numero = ?';
            $params[] = (int) $input['numero'];
        }
        if (array_key_exists('capitaine', $input)) {
            $capitaine = (string) $input['capitaine'];
            if (!in_array($capitaine, ['-', 'C', 'E', 'A', 'X'], true)) {
                return new JsonResponse(['error' => 'Invalid status'], 405);
            }
            $set[] = 'Capitaine = ?';
            $params[] = $capitaine;
        }

        if (empty($set)) {
            return new JsonResponse(['error' => 'No fields to update'], 405);
        }

        $conn = $this->entityManager->getConnection();
        $params[] = $teamId;
        $params[] = $playerId;
        $conn->executeStatement(
            'UPDATE kp_competition_equipe_joueur SET ' . implode(', ', $set) . ' WHERE Id_equipe = ? AND Matric = ?',
            $params
        );

        try {
            $conn->executeStatement(
                "INSERT INTO kp_journal (Dates, Users, Actions, Evenements, Journal) VALUES (NOW(), ?, 'Scrut composition', ?, ?)",
                [$auth['user'], $eventId, "Equipe $teamId - Joueur $playerId - " . json_encode($input)]
            );
        } catch (\Exception) {
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/{eventId}/team/{teamId}/player', name: 'add_composition_player', methods: ['POST'])]
    #[OA\Post(
        path: '/staff/{eventId}/team/{teamId}/player',
        summary: 'Add an existing licensed player to the composition',
        description: 'Adds an existing player (by matric) to the team composition. Blocked if the competition is locked.',
        tags: ['3. App2 - Staff'],
        security: [['ApiToken' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'matric', type: 'integer', example: 123456),
                    new OA\Property(property: 'numero', type: 'integer', nullable: true, example: 12),
                    new OA\Property(property: 'capitaine', type: 'string', nullable: true, enum: ['-', 'C', 'E', 'A', 'X'])
                ]
            )
        ),
        parameters: [
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))
        ],
        responses: [
            new OA\Response(response: 201, description: 'Player added'),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Competition locked'),
            new OA\Response(response: 404, description: 'Player not found'),
            new OA\Response(response: 409, description: 'Player already in composition')
        ]
    )]
    public function addCompositionPlayer(Request $request, int $eventId, int $teamId): JsonResponse
    {
        try {
            $auth = $this->tokenAuthService->validateToken($request, null, $eventId, 3);
        } catch (\RuntimeException) {
            return $this->tokenAuthService->createForbiddenResponse();
        }
        if (!$auth) {
            return $this->tokenAuthService->createUnauthorizedResponse();
        }

        $info = $this->getTeamCompositionInfo($teamId, $eventId);
        if (!$info) {
            return new JsonResponse(['error' => 'Team not found'], 404);
        }
        if ($info['locked']) {
            return new JsonResponse(['error' => 'Competition is locked'], 403);
        }

        $input = json_decode($request->getContent(), true) ?: [];
        $matric = isset($input['matric']) ? (int) $input['matric'] : 0;
        if ($matric <= 0) {
            return new JsonResponse(['error' => 'Matric is required'], 405);
        }
        $numero = isset($input['numero']) ? (int) $input['numero'] : 0;
        $capitaine = (string) ($input['capitaine'] ?? '-');
        if (!in_array($capitaine, ['-', 'C', 'E', 'A', 'X'], true)) {
            return new JsonResponse(['error' => 'Invalid status'], 405);
        }

        $conn = $this->entityManager->getConnection();

        $licence = $conn->fetchAssociative(
            "SELECT Nom, Prenom, Sexe, Naissance FROM kp_licence WHERE Matric = ?",
            [$matric]
        );
        if (!$licence) {
            return new JsonResponse(['error' => 'Player not found in license database'], 404);
        }

        $categ = $this->computeCategory($licence['Naissance'] ?? null, $info['code_saison']);

        try {
            $conn->executeStatement(
                "INSERT INTO kp_competition_equipe_joueur (Id_equipe, Matric, Nom, Prenom, Sexe, Categ, Numero, Capitaine)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [$teamId, $matric, $licence['Nom'], $licence['Prenom'], $licence['Sexe'], $categ, $numero, $capitaine]
            );
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                return new JsonResponse(['error' => 'Player already in composition'], 409);
            }
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        try {
            $conn->executeStatement(
                "INSERT INTO kp_journal (Dates, Users, Actions, Evenements, Journal) VALUES (NOW(), ?, 'Scrut ajout joueur', ?, ?)",
                [$auth['user'], $eventId, "Equipe $teamId - Joueur $matric"]
            );
        } catch (\Exception) {
        }

        return new JsonResponse(['success' => true, 'matric' => $matric], 201);
    }

    #[Route('/{eventId}/team/{teamId}/players/search', name: 'players_search', methods: ['GET'])]
    #[OA\Get(
        path: '/staff/{eventId}/team/{teamId}/players/search',
        summary: 'Search licensed players to add to the composition',
        description: 'Autocomplete on kp_licence (name / matric). Requires at least 2 characters.',
        tags: ['3. App2 - Staff'],
        security: [['ApiToken' => []]],
        parameters: [
            new OA\Parameter(name: 'eventId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'teamId', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
            new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string'))
        ],
        responses: [
            new OA\Response(response: 200, description: 'Matching players'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function searchCompositionPlayers(Request $request, int $eventId, int $teamId): JsonResponse
    {
        try {
            $auth = $this->tokenAuthService->validateToken($request, null, $eventId, 3);
        } catch (\RuntimeException) {
            return $this->tokenAuthService->createForbiddenResponse();
        }
        if (!$auth) {
            return $this->tokenAuthService->createUnauthorizedResponse();
        }

        // Ensure the team belongs to the authenticated event (IDOR protection).
        if (!$this->getTeamCompositionInfo($teamId, $eventId)) {
            return new JsonResponse(['error' => 'Team not found'], 404);
        }

        $query = trim((string) $request->query->get('q', ''));
        if (strlen($query) < 2) {
            return new JsonResponse([]);
        }
        $limit = min(20, max(1, (int) $request->query->get('limit', 10)));

        $conn = $this->entityManager->getConnection();

        // Split on spaces: each word must appear in Nom or Prenom (order-independent).
        // A single numeric token also matches the licence number (Matric).
        $words = preg_split('/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY);
        $conditions = [];
        $params = [];
        foreach ($words as $word) {
            $term = '%' . $word . '%';
            $conditions[] = '(l.Nom LIKE ? OR l.Prenom LIKE ?)';
            $params[] = $term;
            $params[] = $term;
        }
        if (count($words) === 1 && ctype_digit($words[0])) {
            // Replace the last name/first-name condition with a matric-or-name match
            array_pop($conditions);
            array_pop($params);
            array_pop($params);
            $conditions[] = '(l.Nom LIKE ? OR l.Prenom LIKE ? OR l.Matric = ?)';
            $params[] = '%' . $words[0] . '%';
            $params[] = '%' . $words[0] . '%';
            $params[] = (int) $words[0];
        }

        $sql = "SELECT l.Matric matric, l.Nom nom, l.Prenom prenom, l.Numero_club club,
                       COALESCE(c.Libelle, l.Numero_club, '') club_label
                FROM kp_licence l
                LEFT JOIN kp_club c ON l.Numero_club = c.Code
                WHERE " . implode(' AND ', $conditions) . "
                ORDER BY l.Nom, l.Prenom
                LIMIT $limit";

        $rows = $conn->fetchAllAssociative($sql, $params);

        $response = new JsonResponse($rows);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);
        return $response;
    }
}
