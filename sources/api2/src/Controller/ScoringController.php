<?php

namespace App\Controller;

use App\Entity\User;
use App\Trait\AdminLoggableTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Scoring — live match console backend (manual KPI scoring).
 *
 * Replaces the former WsmController ("Web Score Management" — an erroneous translation
 * of WebSocket Manager). The hardware relay keeps the WSM/broker naming; this controller
 * serves the human scoring console (app4 /games/[id]/scoring). See DOC/specs/PAGE_SCORING.md.
 *
 * Routes are under /admin/scoring so they sit behind the existing JWT firewall (^/admin).
 *
 * ⚠️ Experimentation phase: access restricted to ROLE_ADMIN (profile <= 2). Open up to
 * ROLE_SCORER (profile 9 "Table de marque") once validated — see spec §6.3.
 */
#[Route('/admin/scoring', name: 'scoring_')]
#[IsGranted('ROLE_ADMIN')]
class ScoringController extends AbstractController
{
    use AdminLoggableTrait;

    /** DBAL connection — also required by AdminLoggableTrait (expects $this->connection). */
    private Connection $connection;

    /**
     * Match context (season/competition/gameday) of the last assertMatchAuthorized() call,
     * reused to journal the action without re-querying. Reset on each authorized endpoint.
     *
     * @var array{Id_journee:int,Code_saison:?string,Code_competition:?string}|null
     */
    private ?array $matchContext = null;

    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
        $this->connection = $entityManager->getConnection();
    }

    /**
     * Returns a 403/404 JsonResponse if the current mandate is not allowed to score this match,
     * else null. Scope = the match's journée must be within the user's allowed journées
     * (X-Active-Mandate is already resolved into the User by the auth layer). Mirrors
     * AdminGamesController::assertJourneeAuthorized.
     *
     * Side effect: caches the match's season/competition/gameday in $this->matchContext so the
     * mutating endpoints can journal via logActionForMatch() without an extra query.
     */
    private function assertMatchAuthorized(int $matchId): ?JsonResponse
    {
        $this->matchContext = null;

        $row = $this->connection->fetchAssociative(
            "SELECT m.Id_journee, j.Code_saison, j.Code_competition
             FROM kp_match m INNER JOIN kp_journee j ON m.Id_journee = j.Id
             WHERE m.Id = ?",
            [$matchId]
        );

        if ($row === false) {
            return new JsonResponse(['error' => 'Match not found'], Response::HTTP_NOT_FOUND);
        }

        /** @var User|null $user */
        $user = $this->getUser();
        $allowed = $user?->getAllowedJournees();
        if ($allowed !== null && !in_array((int) $row['Id_journee'], $allowed, true)) {
            return new JsonResponse(['error' => 'Access denied for this match'], Response::HTTP_FORBIDDEN);
        }

        $this->matchContext = [
            'Id_journee' => (int) $row['Id_journee'],
            'Code_saison' => $row['Code_saison'],
            'Code_competition' => $row['Code_competition'],
        ];

        return null;
    }

    /**
     * Normalize a game time to the HH:MM:SS form stored in kp_match_detail.Temps (a TIME column).
     * The console works in MM:SS (a half lasts ≤ 10 min). Legacy (v2/evt_match.php) prefixed
     * '00:' before insert; we do the same so MySQL doesn't read "01:28" as 1h28. Already-3-part
     * values are passed through.
     */
    private function normalizeTemps(string $tpsJeu): string
    {
        $tpsJeu = trim($tpsJeu);
        return substr_count($tpsJeu, ':') >= 2 ? $tpsJeu : '00:' . $tpsJeu;
    }

    /**
     * Journal a scoring action for the match resolved by the last assertMatchAuthorized() call.
     * No-op (silent) if no context — never breaks the main operation, like the trait itself.
     */
    private function logScoring(string $action, int $matchId, ?string $details = null): void
    {
        $ctx = $this->matchContext;
        if ($ctx === null) {
            return;
        }
        $this->logActionForMatch(
            $action,
            $ctx['Code_saison'],
            $ctx['Code_competition'],
            $ctx['Id_journee'],
            $matchId,
            $details
        );
    }

    #[Route('/gameParam/{matchId}', name: 'game_param', methods: ['PUT'])]
    #[OA\Put(
        path: '/admin/scoring/gameParam/{matchId}',
        summary: 'Update game parameters (status, period, scores)',
        tags: ['6. Scoring'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['param', 'value'],
                properties: [
                    new OA\Property(
                        property: 'param',
                        type: 'string',
                        enum: ['Statut', 'Periode', 'ScoreA', 'ScoreB', 'ScoreDetailA', 'ScoreDetailB', 'Heure_fin']
                    ),
                    new OA\Property(property: 'value', type: 'string', example: '5')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Parameter updated'),
            new OA\Response(response: 400, description: 'Game is locked (validated)'),
            new OA\Response(response: 401, description: 'Invalid parameter')
        ]
    )]
    public function putGameParam(int $matchId, Request $request): JsonResponse
    {
        if ($err = $this->assertMatchAuthorized($matchId)) return $err;

        $data = json_decode($request->getContent());

        if (!in_array($data->param ?? '', ['Statut', 'Periode', 'ScoreA', 'ScoreB', 'ScoreDetailA', 'ScoreDetailB', 'Heure_fin'])) {
            return new JsonResponse(['error' => 'Invalid parameter'], 401);
        }

        $sql = "UPDATE kp_match
            SET {$data->param} = ?
            WHERE Id = ?
            AND Validation != 'O'";

        try {
            $this->connection->executeStatement($sql, [$data->value, $matchId]);

            // Journal: "Scoring score/statut/période" depending on the param
            $label = match ($data->param) {
                'Statut' => 'Scoring statut',
                'Periode' => 'Scoring période',
                default => 'Scoring score',
            };
            $this->logScoring($label, $matchId, "{$data->param} = {$data->value}");

            // TODO (Phase 3): generate broadcast cache here
            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/gameEvent/{matchId}', name: 'game_event', methods: ['PUT'])]
    #[OA\Put(
        path: '/admin/scoring/gameEvent/{matchId}',
        summary: 'Add or remove match events (goals, cards)',
        tags: ['6. Scoring'],
        responses: [
            new OA\Response(response: 200, description: 'Event added/removed'),
            new OA\Response(response: 400, description: 'Game is locked')
        ]
    )]
    public function putGameEvent(int $matchId, Request $request): JsonResponse
    {
        if ($err = $this->assertMatchAuthorized($matchId)) return $err;

        $data = json_decode($request->getContent());

        $sql = "SELECT COUNT(Id) FROM kp_match WHERE Id = ? AND Validation != 'O'";
        $count = $this->connection->fetchOne($sql, [$matchId]);

        if ($count != 1) {
            return new JsonResponse(['error' => 'Game locked'], 400);
        }

        $p = $data->params;
        $action = $p->action ?? '';

        if ($action === 'add') {
            $uid = $p->uid ?? str_replace('-', '', uniqid('', true));

            $sql = "INSERT INTO kp_match_detail (Id, Id_match, Periode, Temps, Id_evt_match,
                Competiteur, Numero, Equipe_A_B, motif)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $this->connection->executeStatement($sql, [
                $uid, $matchId, $p->period, $this->normalizeTemps($p->tpsJeu), $p->code,
                $p->player, $p->number, $p->team, $p->reason
            ]);

            $this->logScoring('Scoring événement', $matchId, "add {$p->code} #{$p->number} {$p->period} {$p->tpsJeu}");
        } elseif ($action === 'update') {
            // Edit an existing event in place (period/time/player/number/code/team/reason),
            // located by its uid. Central to post-match correction (spec §7.3).
            if (empty($p->uid)) {
                return new JsonResponse(['error' => 'uid required for update'], 400);
            }

            $sql = "UPDATE kp_match_detail
                SET Periode = ?, Temps = ?, Id_evt_match = ?, Competiteur = ?,
                    Numero = ?, Equipe_A_B = ?, motif = ?
                WHERE Id = ? AND Id_match = ?";

            $this->connection->executeStatement($sql, [
                $p->period, $this->normalizeTemps($p->tpsJeu), $p->code, $p->player,
                $p->number, $p->team, $p->reason, $p->uid, $matchId
            ]);

            $this->logScoring('Scoring événement', $matchId, "update {$p->uid} {$p->code} #{$p->number} {$p->period} {$p->tpsJeu}");
        } elseif ($action === 'remove') {
            // Prefer removal by uid (precise); fall back to the legacy match-by-fields delete.
            if (!empty($p->uid)) {
                $this->connection->executeStatement(
                    "DELETE FROM kp_match_detail WHERE Id = ? AND Id_match = ?",
                    [$p->uid, $matchId]
                );
                $this->logScoring('Scoring événement', $matchId, "remove {$p->uid}");
            } else {
                $sql = "DELETE FROM kp_match_detail
                    WHERE Id_match = ?
                    AND Periode = ?
                    AND Competiteur = ?
                    AND Id_evt_match = ?
                    ORDER BY date_insert DESC
                    LIMIT 1";

                $this->connection->executeStatement($sql, [
                    $matchId, $p->period, $p->player, $p->code
                ]);
                $this->logScoring('Scoring événement', $matchId, "remove {$p->code} {$p->period} player {$p->player}");
            }
        } else {
            return new JsonResponse(['error' => 'Invalid action'], 400);
        }

        // TODO (Phase 3): generate broadcast cache here
        return new JsonResponse(['success' => true]);
    }

    #[Route('/events/{matchId}', name: 'game_events_get', methods: ['GET'])]
    #[OA\Get(
        path: '/admin/scoring/events/{matchId}',
        summary: 'List the recorded events of a match (goals, cards) for the scoring console',
        tags: ['6. Scoring'],
        responses: [
            new OA\Response(response: 200, description: 'Events ordered period DESC, time ASC')
        ]
    )]
    public function getGameEvents(int $matchId): JsonResponse
    {
        if ($err = $this->assertMatchAuthorized($matchId)) return $err;

        // Mirrors ReportController event enrichment (player name from kp_licence).
        // Temps is a TIME column stored as 00:MM:SS (legacy prefixes '00:'); the console works
        // in MM:SS, so format it back to MM:SS here (a half lasts ≤ 10 min — no hours to show).
        $rows = $this->connection->fetchAllAssociative(
            "SELECT md.Id uid, md.Periode period, TIME_FORMAT(md.Temps, '%i:%s') tpsJeu,
                md.Id_evt_match code, md.Competiteur player, md.Numero number,
                md.Equipe_A_B team, md.motif reason, l.Nom nom, l.Prenom prenom
             FROM kp_match_detail md
             LEFT OUTER JOIN kp_licence l ON (md.Competiteur = l.Matric)
             WHERE md.Id_match = ?
             ORDER BY md.Periode DESC, md.Temps ASC, md.date_insert ASC",
            [$matchId]
        );

        $response = new JsonResponse(['events' => $rows]);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);
        return $response;
    }

    #[Route('/playerStatus/{matchId}', name: 'player_status', methods: ['PUT'])]
    #[OA\Put(
        path: '/admin/scoring/playerStatus/{matchId}',
        summary: 'Update player status (Captain, Coach)',
        tags: ['6. Scoring'],
        responses: [
            new OA\Response(response: 200, description: 'Player status updated'),
            new OA\Response(response: 400, description: 'Game is locked or invalid parameters')
        ]
    )]
    public function putPlayerStatus(int $matchId, Request $request): JsonResponse
    {
        if ($err = $this->assertMatchAuthorized($matchId)) return $err;

        $data = json_decode($request->getContent());

        $sql = "SELECT COUNT(Id) FROM kp_match WHERE Id = ? AND Validation != 'O'";
        $count = $this->connection->fetchOne($sql, [$matchId]);

        if ($count != 1) {
            return new JsonResponse(['error' => 'Game locked'], 400);
        }

        if ($data->params->team && $data->params->player && $data->params->status) {
            $sql = "UPDATE kp_match_joueur
                SET Capitaine = ?
                WHERE Id_match = ?
                AND Equipe = ?
                AND Matric = ?";

            $this->connection->executeStatement($sql, [
                $data->params->status, $matchId, $data->params->team, $data->params->player
            ]);

            $this->logScoring('Scoring joueur', $matchId, "{$data->params->team} player {$data->params->player} → {$data->params->status}");

            return new JsonResponse(['success' => true]);
        }

        return new JsonResponse(['error' => 'Invalid parameters'], 400);
    }

    #[Route('/gameTimer/{matchId}', name: 'game_timer_get', methods: ['GET'])]
    #[OA\Get(
        path: '/admin/scoring/gameTimer/{matchId}',
        summary: 'Read persisted timer state (for clock restore on reload)',
        tags: ['6. Scoring'],
        responses: [
            new OA\Response(response: 200, description: 'Timer state (null action if none)')
        ]
    )]
    public function getGameTimer(int $matchId): JsonResponse
    {
        if ($err = $this->assertMatchAuthorized($matchId)) return $err;

        $row = $this->connection->fetchAssociative(
            "SELECT `action`, start_time, start_time_server, run_time, max_time
             FROM kp_chrono WHERE IdMatch = ?",
            [$matchId]
        );

        // Server time in seconds since midnight (same basis as start_time_server)
        $nowServer = time() % 86400;

        if (!$row) {
            return new JsonResponse(['action' => null, 'nowServer' => $nowServer]);
        }

        return new JsonResponse([
            'action' => $row['action'],
            'startTime' => (int) $row['start_time'],
            'startTimeServer' => $row['start_time_server'] !== null ? (int) $row['start_time_server'] : null,
            'runTime' => (int) $row['run_time'],
            'maxTime' => (int) $row['max_time'],
            'nowServer' => $nowServer,
        ]);
    }

    #[Route('/gameTimer/{matchId}', name: 'game_timer', methods: ['PUT'])]
    #[OA\Put(
        path: '/admin/scoring/gameTimer/{matchId}',
        summary: 'Control match timer (run/stop/RAZ)',
        tags: ['6. Scoring'],
        responses: [
            new OA\Response(response: 200, description: 'Timer updated'),
            new OA\Response(response: 400, description: 'Game is locked'),
            new OA\Response(response: 401, description: 'Invalid action')
        ]
    )]
    public function putGameTimer(int $matchId, Request $request): JsonResponse
    {
        if ($err = $this->assertMatchAuthorized($matchId)) return $err;

        $data = json_decode($request->getContent());

        if (!in_array($data->params->action ?? '', ['run', 'stop', 'RAZ'])) {
            return new JsonResponse(['error' => 'Invalid action'], 401);
        }

        $sql = "SELECT COUNT(Id) FROM kp_match WHERE Id = ? AND Validation != 'O'";
        $count = $this->connection->fetchOne($sql, [$matchId]);

        if ($count != 1) {
            return new JsonResponse(['error' => 'Game locked'], 400);
        }

        if ($data->params->action === 'RAZ') {
            $sql = "DELETE FROM kp_chrono WHERE IdMatch = ?";
            $this->connection->executeStatement($sql, [$matchId]);
        } else {
            $data->params->startTimeServer = time() % 86400;
            $sql = "REPLACE kp_chrono
                SET IdMatch = ?,
                `action` = ?,
                start_time = ?,
                start_time_server = ?,
                run_time = ?,
                max_time = ?";

            $this->connection->executeStatement($sql, [
                $matchId, $data->params->action, $data->params->startTime, $data->params->startTimeServer,
                $data->params->runTime, $data->params->maxTime
            ]);
        }

        $this->logScoring('Scoring chrono', $matchId, $data->params->action);

        // TODO (Phase 3): generate broadcast cache here
        return new JsonResponse(['success' => true]);
    }

    #[Route('/stats', name: 'stats', methods: ['PUT'])]
    #[OA\Put(
        path: '/admin/scoring/stats',
        summary: 'Record match statistics',
        tags: ['6. Scoring'],
        responses: [
            new OA\Response(response: 200, description: 'Statistic recorded'),
            new OA\Response(response: 400, description: 'Game is locked'),
            new OA\Response(response: 401, description: 'Invalid action')
        ]
    )]
    public function putStats(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent());

        if (!in_array($data->action ?? '', ['pass', 'possession', 'kickoff', 'kickoff-ko', 'shot-in', 'shot-out', 'shot-stop'])) {
            return new JsonResponse(['error' => 'Invalid action'], 401);
        }

        if ($err = $this->assertMatchAuthorized((int) ($data->game ?? 0))) return $err;

        $sql = "SELECT COUNT(Id) FROM kp_match WHERE Id = ? AND Validation != 'O'";
        $count = $this->connection->fetchOne($sql, [$data->game]);

        if ($count != 1) {
            return new JsonResponse(['error' => 'Game locked'], 400);
        }

        $sql = "INSERT INTO kp_stats
            SET user = ?,
            game = ?,
            team = ?,
            player = ?,
            `action` = ?,
            `period` = ?,
            timer = ?";

        $this->connection->executeStatement($sql, [
            $data->user, $data->game, $data->team, $data->player,
            $data->action, $data->period, rtrim($data->timer, '.')
        ]);

        return new JsonResponse(['success' => true]);
    }
}
