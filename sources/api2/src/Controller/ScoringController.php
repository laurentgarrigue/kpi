<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ScoringLiveService;
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
 * Storage (lot 1 of the refactoring plan): live writes go to the canonical scoring_live_*
 * tables via ScoringLiveService — NOT to kp_* anymore. kp_* is only written back by the
 * end-of-match consolidation (Statut → END). Endpoints and payloads are unchanged, so the
 * console UI did not move. See LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md.
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
        private EntityManagerInterface $entityManager,
        private ScoringLiveService $live
    ) {
        $this->connection = $entityManager->getConnection();
    }

    /**
     * Enforce plan §4.1 (single active source): the console writes as MANUAL; if another
     * source has been promoted on this match, the command is journalized but NOT applied.
     * Returns a 409 JsonResponse to send back, or null when writing is allowed.
     */
    private function assertSourceAllowed(int $matchId, string $detail): ?JsonResponse
    {
        $state = $this->live->ensureState($matchId);
        if ($this->live->isSourceAllowed($state, 'MANUAL')) {
            return null;
        }

        $this->logScoring('Scoring rejeté (source)', $matchId, $detail . ' — source active: ' . $state['active_source']);

        return new JsonResponse(
            ['error' => 'Another source is active for this match', 'activeSource' => $state['active_source']],
            Response::HTTP_CONFLICT
        );
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

        $locked = $this->connection->fetchOne(
            "SELECT COUNT(Id) FROM kp_match WHERE Id = ? AND Validation != 'O'",
            [$matchId]
        );
        if ($locked != 1) {
            return new JsonResponse(['error' => 'Game locked'], 400);
        }

        try {
            if (in_array($data->param, ['ScoreA', 'ScoreB'], true)) {
                // Official score validation is a result concern, not live state: it keeps
                // writing kp_match directly (spec §7.6 — "Valider ce score").
                $this->connection->executeStatement(
                    "UPDATE kp_match SET {$data->param} = ? WHERE Id = ? AND Validation != 'O'",
                    [$data->value, $matchId]
                );
            } else {
                // Live state → scoring_live_state (+ tick + outbox, one transaction).
                // Statut → END also consolidates back to kp_* (plan §4.3).
                if ($err = $this->assertSourceAllowed($matchId, "{$data->param} = {$data->value}")) return $err;
                $this->live->setParam($matchId, $data->param, (string) $data->value);
            }

            // Journal: "Scoring score/statut/période" depending on the param
            $label = match ($data->param) {
                'Statut' => 'Scoring statut',
                'Periode' => 'Scoring période',
                default => 'Scoring score',
            };
            $this->logScoring($label, $matchId, "{$data->param} = {$data->value}");
            if ($data->param === 'Statut' && $data->value === 'END') {
                $this->logScoring('Scoring consolidation', $matchId, 'Statut END → kp_*');
            }

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

        if (!in_array($action, ['add', 'update', 'remove'], true)) {
            return new JsonResponse(['error' => 'Invalid action'], 400);
        }
        if ($action === 'update' && empty($p->uid)) {
            // Edit in place, located by uid — central to post-match correction (spec §7.3).
            return new JsonResponse(['error' => 'uid required for update'], 400);
        }

        if ($err = $this->assertSourceAllowed($matchId, "event $action")) return $err;

        $uid = $p->uid ?? str_replace('-', '', uniqid('', true));

        // Live facts → scoring_live_event (+ tick + outbox on the /fact topic).
        // kp_match_detail is only rebuilt at consolidation (Statut → END).
        $this->live->writeEvent($matchId, $action, [
            'uid' => $action === 'add' ? $uid : ($p->uid ?? null),
            'code' => $p->code ?? null,
            'period' => $p->period ?? null,
            'temps' => isset($p->tpsJeu) ? $this->normalizeTemps($p->tpsJeu) : null,
            'tpsJeu' => $p->tpsJeu ?? null,
            'team' => $p->team ?? null,
            'player' => $p->player ?? null,
            'number' => $p->number ?? null,
            'reason' => $p->reason ?? null,
        ]);

        $detail = match ($action) {
            'add' => "add {$p->code} #{$p->number} {$p->period} {$p->tpsJeu}",
            'update' => "update {$p->uid} {$p->code} #{$p->number} {$p->period} {$p->tpsJeu}",
            'remove' => !empty($p->uid) ? "remove {$p->uid}" : "remove {$p->code} {$p->period} player {$p->player}",
        };
        $this->logScoring('Scoring événement', $matchId, $detail);

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

        // Live facts from scoring_live_event (player name from kp_licence, as before).
        // Temps is stored 00:MM:SS (legacy convention kept); format back to MM:SS.
        $rows = $this->connection->fetchAllAssociative(
            "SELECT e.uid, e.periode period, TIME_FORMAT(e.temps, '%i:%s') tpsJeu,
                e.code, e.id_player player, e.numero number,
                e.team, e.motif reason, l.Nom nom, l.Prenom prenom
             FROM scoring_live_event e
             LEFT OUTER JOIN kp_licence l ON (e.id_player = l.Matric)
             WHERE e.id_match = ?
             ORDER BY e.periode DESC, e.temps ASC, e.created_at ASC",
            [$matchId]
        );

        // Transition: a match never touched by the new flow has no live rows yet — fall
        // back to the legacy detail table (read-only; the live table is seeded on the
        // first mutation, see ScoringLiveService::ensureState).
        if ($rows === []) {
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
        }

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

        // Server time in seconds since midnight (same basis as start_time_server)
        $nowServer = time() % 86400;

        // Live clock (kind GAME) first — same response contract as the kp_chrono era so
        // the console restore logic is untouched.
        $clock = $this->connection->fetchAssociative(
            "SELECT init_ms, elapsed_ms, started_at, running
             FROM scoring_live_clock
             WHERE id_match = ? AND kind = 'GAME'",
            [$matchId]
        );

        if ($clock !== false) {
            $elapsed = (int) round(((int) $clock['elapsed_ms']) / 1000);

            return new JsonResponse([
                'action' => ((bool) $clock['running']) ? 'run' : 'stop',
                'startTime' => $elapsed,
                'startTimeServer' => $clock['started_at'] !== null
                    ? strtotime($clock['started_at']) % 86400
                    : null,
                'runTime' => $elapsed,
                'maxTime' => (int) round(((int) $clock['init_ms']) / 1000),
                'nowServer' => $nowServer,
            ]);
        }

        // Transition fallback: matches last driven under the legacy flow.
        $row = $this->connection->fetchAssociative(
            "SELECT `action`, start_time, start_time_server, run_time, max_time
             FROM kp_chrono WHERE IdMatch = ?",
            [$matchId]
        );

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

        if ($err = $this->assertSourceAllowed($matchId, 'timer ' . ($data->params->action ?? ''))) return $err;

        // Clock kind defaults to the main game clock; the same endpoint drives the
        // shotclock, penalties and inter-period breaks (spec §0.5 — N clocks per match).
        $kind = strtoupper($data->params->kind ?? 'GAME');
        $team = (string) ($data->params->team ?? '');
        $slot = (int) ($data->params->slot ?? 0);

        if ($data->params->action === 'RAZ') {
            $this->live->removeClock($matchId, $kind, $team, $slot);
        } else {
            // Payload stays in seconds (unchanged endpoint contract); storage is ms.
            $this->live->setClock($matchId, [
                'kind' => $kind,
                'team' => $team,
                'slot' => $slot,
                'playerId' => $data->params->playerId ?? null,
                'cardCode' => $data->params->cardCode ?? null,
                'initMs' => (int) round(((float) ($data->params->maxTime ?? 0)) * 1000),
                'elapsedMs' => (int) round(((float) ($data->params->startTime ?? 0)) * 1000),
                'running' => $data->params->action === 'run',
            ]);
        }

        $this->logScoring('Scoring chrono', $matchId, trim("{$kind} {$data->params->action} {$team}{$slot}", ' 0'));

        return new JsonResponse(['success' => true]);
    }

    #[Route('/state/{matchId}', name: 'state_get', methods: ['GET'])]
    #[OA\Get(
        path: '/admin/scoring/state/{matchId}',
        summary: 'Full canonical live state of a match (state + clocks + facts)',
        tags: ['6. Scoring'],
        responses: [
            new OA\Response(response: 200, description: 'Live state (exists = false when the match was never touched live)'),
            new OA\Response(response: 304, description: 'Not modified (If-None-Match / tick)')
        ]
    )]
    public function getState(int $matchId, Request $request): JsonResponse
    {
        if ($err = $this->assertMatchAuthorized($matchId)) return $err;

        $state = $this->live->getState($matchId);

        if ($state === null) {
            return new JsonResponse(['exists' => false, 'matchId' => $matchId]);
        }

        // Cheap HTTP cache: the tick IS the version (plan §1.3). 304 when unchanged.
        $etag = 'W/"scoring-' . $matchId . '-' . $state['tick'] . '"';
        if ($request->headers->get('If-None-Match') === $etag) {
            return new JsonResponse(null, Response::HTTP_NOT_MODIFIED);
        }

        $response = new JsonResponse(['exists' => true] + $state + ['nowServer' => time() % 86400]);
        $response->headers->set('ETag', $etag);
        $response->setEncodingOptions($response->getEncodingOptions() | JSON_UNESCAPED_UNICODE);

        return $response;
    }

    #[Route('/source/{matchId}', name: 'source_put', methods: ['PUT'])]
    #[OA\Put(
        path: '/admin/scoring/source/{matchId}',
        summary: 'Promote the active writing source of a match (plan §4.1)',
        tags: ['6. Scoring'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['source'],
                properties: [
                    new OA\Property(property: 'source', type: 'string', enum: ['MANUAL', 'HARDWARE', 'SCORE_ONLY', 'IMPORT'])
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Source promoted (timestamped, journalized)'),
            new OA\Response(response: 400, description: 'Unknown source or locked game')
        ]
    )]
    public function putSource(int $matchId, Request $request): JsonResponse
    {
        if ($err = $this->assertMatchAuthorized($matchId)) return $err;

        $data = json_decode($request->getContent());
        $source = strtoupper((string) ($data->source ?? ''));

        $locked = $this->connection->fetchOne(
            "SELECT COUNT(Id) FROM kp_match WHERE Id = ? AND Validation != 'O'",
            [$matchId]
        );
        if ($locked != 1) {
            return new JsonResponse(['error' => 'Game locked'], 400);
        }

        try {
            $tick = $this->live->promoteSource($matchId, $source);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        $this->logScoring('Scoring source', $matchId, "promotion → {$source}");

        return new JsonResponse(['success' => true, 'activeSource' => $source, 'tick' => $tick]);
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
