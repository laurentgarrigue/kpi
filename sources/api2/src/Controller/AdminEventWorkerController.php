<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/events/worker')]
#[IsGranted('ROLE_ADMIN')]
#[OA\Tag(name: '36. App4 - Event Cache Worker')]
class AdminEventWorkerController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    // ─────────────────────────────────────────────
    // GET /admin/events/worker/status
    // ─────────────────────────────────────────────

    #[Route('/status', name: 'admin_event_worker_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        return $this->json($this->fetchActiveConfigs());
    }

    // ─────────────────────────────────────────────
    // POST /admin/events/worker/start
    // ─────────────────────────────────────────────

    #[Route('/start', name: 'admin_event_worker_start', methods: ['POST'])]
    public function start(Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $idEvent   = isset($body['idEvent'])   ? (int) $body['idEvent']   : 0;
        $dateEvent = trim($body['dateEvent']   ?? '');
        $hourEvent = trim($body['hourEvent']   ?? '');

        if ($idEvent <= 0 || $dateEvent === '' || $hourEvent === '') {
            return $this->json(['message' => 'idEvent, dateEvent and hourEvent are required'], Response::HTTP_BAD_REQUEST);
        }

        // Normalize HH:MM → HH:MM:SS
        if (preg_match('/^\d{2}:\d{2}$/', $hourEvent)) {
            $hourEvent .= ':00';
        }

        $offsetEvent = isset($body['offsetEvent']) ? (int) $body['offsetEvent'] : 15;
        $pitchEvent  = isset($body['pitchEvent'])  ? (int) $body['pitchEvent']  : 4;
        $delayEvent  = isset($body['delayEvent'])  ? (int) $body['delayEvent']  : 10;

        $existing = $this->connection->fetchAssociative(
            "SELECT id FROM kp_event_worker_config WHERE id_event = ? AND status IN ('running','paused')",
            [$idEvent]
        );

        if ($existing !== false) {
            // created_at is the simulation anchor: reset it so the simulated clock
            // restarts from hour_event_initial (execution_count is reset too).
            $this->connection->executeStatement(
                "UPDATE kp_event_worker_config
                 SET status = 'running', date_event = ?, hour_event = ?, hour_event_initial = ?,
                     offset_event = ?, pitch_event = ?, delay_event = ?, execution_count = 0,
                     error_message = NULL, created_at = NOW(), updated_at = NOW()
                 WHERE id = ?",
                [$dateEvent, $hourEvent, $hourEvent, $offsetEvent, $pitchEvent, $delayEvent, $existing['id']]
            );
            $id = $existing['id'];
        } else {
            $this->connection->executeStatement(
                "INSERT INTO kp_event_worker_config
                    (id_event, date_event, hour_event, hour_event_initial, offset_event, pitch_event, delay_event, status, execution_count, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'running', 0, NOW(), NOW())",
                [$idEvent, $dateEvent, $hourEvent, $hourEvent, $offsetEvent, $pitchEvent, $delayEvent]
            );
            $id = (int) $this->connection->lastInsertId();
        }

        $row = $this->connection->fetchAssociative(
            "SELECT *, UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(last_execution) AS seconds_since_last_execution
             FROM kp_event_worker_config WHERE id = ?",
            [$id]
        );

        return $this->json($this->enrichConfig($row));
    }

    // ─────────────────────────────────────────────
    // POST /admin/events/worker/stop (all)
    // ─────────────────────────────────────────────

    #[Route('/stop', name: 'admin_event_worker_stop_all', methods: ['POST'])]
    public function stopAll(): JsonResponse
    {
        $this->connection->executeStatement(
            "UPDATE kp_event_worker_config SET status = 'stopped', updated_at = NOW()
             WHERE status IN ('running','paused')"
        );

        return $this->json($this->fetchActiveConfigs());
    }

    // ─────────────────────────────────────────────
    // POST /admin/events/worker/{idEvent}/stop
    // ─────────────────────────────────────────────

    #[Route('/{idEvent}/stop', name: 'admin_event_worker_stop_one', methods: ['POST'], requirements: ['idEvent' => '\d+'])]
    public function stopOne(int $idEvent): JsonResponse
    {
        $this->connection->executeStatement(
            "UPDATE kp_event_worker_config SET status = 'stopped', updated_at = NOW()
             WHERE id_event = ? AND status IN ('running','paused')",
            [$idEvent]
        );

        return $this->json($this->fetchActiveConfigs());
    }

    // ─────────────────────────────────────────────
    // POST /admin/events/worker/{idEvent}/pause
    // ─────────────────────────────────────────────

    #[Route('/{idEvent}/pause', name: 'admin_event_worker_pause', methods: ['POST'], requirements: ['idEvent' => '\d+'])]
    public function pause(int $idEvent): JsonResponse
    {
        $this->connection->executeStatement(
            "UPDATE kp_event_worker_config SET status = 'paused', updated_at = NOW()
             WHERE id_event = ? AND status = 'running'",
            [$idEvent]
        );

        return $this->json($this->fetchActiveConfigs());
    }

    // ─────────────────────────────────────────────
    // POST /admin/events/worker/{idEvent}/resume
    // ─────────────────────────────────────────────

    #[Route('/{idEvent}/resume', name: 'admin_event_worker_resume', methods: ['POST'], requirements: ['idEvent' => '\d+'])]
    public function resume(int $idEvent): JsonResponse
    {
        $this->connection->executeStatement(
            "UPDATE kp_event_worker_config SET status = 'running', updated_at = NOW()
             WHERE id_event = ? AND status = 'paused'",
            [$idEvent]
        );

        return $this->json($this->fetchActiveConfigs());
    }

    // ─────────────────────────────────────────────
    // PATCH /admin/events/worker/{idEvent}
    // ─────────────────────────────────────────────

    #[Route('/{idEvent}', name: 'admin_event_worker_update', methods: ['PATCH'], requirements: ['idEvent' => '\d+'])]
    public function update(int $idEvent, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true);
        if (!is_array($body)) {
            return $this->json(['message' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $sets  = [];
        $params = [];

        if (isset($body['offsetEvent'])) {
            $sets[]   = 'offset_event = ?';
            $params[] = (int) $body['offsetEvent'];
        }
        if (isset($body['pitchEvent'])) {
            $sets[]   = 'pitch_event = ?';
            $params[] = (int) $body['pitchEvent'];
        }
        if (isset($body['delayEvent'])) {
            $sets[]   = 'delay_event = ?';
            $params[] = (int) $body['delayEvent'];
        }

        if (empty($sets)) {
            return $this->json(['message' => 'At least one of offsetEvent, pitchEvent, delayEvent is required'], Response::HTTP_BAD_REQUEST);
        }

        $params[] = $idEvent;
        $this->connection->executeStatement(
            'UPDATE kp_event_worker_config SET ' . implode(', ', $sets) . ', updated_at = NOW()
             WHERE id_event = ? AND status IN (\'running\',\'paused\')',
            $params
        );

        $row = $this->connection->fetchAssociative(
            "SELECT *, UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(last_execution) AS seconds_since_last_execution
             FROM kp_event_worker_config WHERE id_event = ? AND status IN ('running','paused')
             ORDER BY id DESC",
            [$idEvent]
        );

        if ($row === false) {
            return $this->json(['message' => 'Worker config not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->enrichConfig($row));
    }

    // ─────────────────────────────────────────────
    // GET /admin/events/worker/{idEvent}/dates
    // ─────────────────────────────────────────────

    #[Route('/{idEvent}/dates', name: 'admin_event_worker_dates', methods: ['GET'], requirements: ['idEvent' => '\d+'])]
    public function dates(int $idEvent): JsonResponse
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT m.Date_match AS dateMatch, MIN(m.Heure_match) AS heureMatch
             FROM kp_match m
             LEFT JOIN kp_evenement_journee ej ON m.Id_journee = ej.Id_journee
             WHERE ej.Id_evenement = ?
             GROUP BY m.Date_match
             ORDER BY m.Date_match",
            [$idEvent]
        );

        return $this->json(array_map(fn(array $r) => [
            'dateMatch'  => $r['dateMatch'],
            'heureMatch' => $r['heureMatch'],
        ], $rows));
    }

    // ─────────────────────────────────────────────
    // GET /admin/events/worker/{idEvent}/monitor
    // ─────────────────────────────────────────────

    #[Route('/{idEvent}/monitor', name: 'admin_event_worker_monitor', methods: ['GET'], requirements: ['idEvent' => '\d+'])]
    public function monitor(int $idEvent, Request $request): JsonResponse
    {
        $dateEvent   = $request->query->get('dateEvent', '');
        $hourEvent   = $request->query->get('hourEvent', '');
        $offsetEvent = (int) $request->query->get('offsetEvent', 15);
        $pitchEvent  = (int) $request->query->get('pitchEvent', 4);

        if ($dateEvent === '' || $hourEvent === '') {
            return $this->json(['message' => 'dateEvent and hourEvent are required'], Response::HTTP_BAD_REQUEST);
        }

        // Build pitch list 1..pitchEvent
        $pitches = range(1, $pitchEvent);
        $in = implode(',', array_fill(0, count($pitches), '?'));

        $rows = $this->connection->fetchAllAssociative(
            "SELECT a.Id, a.Numero_ordre, a.Terrain, a.Heure_match, a.Statut
             FROM kp_match a
             JOIN kp_journee b ON a.Id_journee = b.Id
             JOIN kp_evenement_journee c ON b.Id = c.Id_journee
             WHERE c.Id_evenement = ?
               AND a.Date_match = ?
               AND a.Publication = 'O'
               AND a.Terrain IN ($in)
             ORDER BY a.Heure_match, a.Terrain",
            array_merge([$idEvent, $dateEvent], $pitches)
        );

        // Calculate working time = hourEvent + offsetEvent minutes
        $currentMinutes = $this->hhmmToMinutes($hourEvent);
        $workingMinutes = $currentMinutes + $offsetEvent;

        $result = [];
        foreach ($pitches as $pitch) {
            $best = $this->getBestMatch($rows, (string) $pitch, $workingMinutes);
            $next = $this->getNextMatch($rows, (string) $pitch, $workingMinutes);
            $result[] = [
                'pitch' => (string) $pitch,
                'game'  => $best['id'],
                'num'   => $best['num'],
                'time'  => $best['time'],
                'next'  => $next,
            ];
        }

        return $this->json([
            'pitches' => $result,
            'time'    => [
                'currentTime' => $this->minutesToHHMM($currentMinutes),
                'workingTime' => $this->minutesToHHMM($workingMinutes),
            ],
        ]);
    }

    // ─────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────

    private function fetchActiveConfigs(): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT *, UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(last_execution) AS seconds_since_last_execution
             FROM kp_event_worker_config
             WHERE status IN ('running','paused')
             ORDER BY id_event ASC"
        );

        return array_map(fn(array $r) => $this->enrichConfig($r), $rows);
    }

    private function enrichConfig(array $config): array
    {
        $config['isRunning'] = $config['status'] === 'running';
        $config['isPaused']  = $config['status'] === 'paused';
        $config['isStopped'] = $config['status'] === 'stopped';

        if ($config['status'] === 'running' || $config['status'] === 'paused') {
            // Simulated time = initial event time + real seconds elapsed since the
            // worker was (re)started. created_at is the single shared anchor: it is
            // reset to NOW() on every start (see start()), and the worker daemon
            // reads the very same created_at as its clock origin, so this value and
            // the JSON the daemon generates stay in lock-step (no drift, no × delay
            // over/under-estimate).
            $initialTime    = strtotime($config['date_event'] . ' ' . $config['hour_event_initial']);
            $startedAt      = strtotime($config['created_at']);
            $elapsedSeconds = max(0, time() - $startedAt);
            $config['currentSimulatedTime'] = date('H:i:s', $initialTime + $elapsedSeconds);
        } else {
            $config['currentSimulatedTime'] = $config['hour_event'];
        }

        $secondsSince = $config['seconds_since_last_execution'];
        if ($config['last_execution'] && $secondsSince !== null) {
            $config['isHealthy'] = (int) $secondsSince < ((int) $config['delay_event'] * 3);
        } else {
            $config['isHealthy'] = true;
        }

        return [
            'id'                       => (int) $config['id'],
            'idEvent'                  => (int) $config['id_event'],
            'dateEvent'                => $config['date_event'],
            'hourEvent'                => $config['hour_event'],
            'hourEventInitial'         => $config['hour_event_initial'],
            'offsetEvent'              => (int) $config['offset_event'],
            'pitchEvent'               => (int) $config['pitch_event'],
            'delayEvent'               => (int) $config['delay_event'],
            'status'                   => $config['status'],
            'lastExecution'            => $config['last_execution'],
            'createdAt'                => $config['created_at'],
            'updatedAt'                => $config['updated_at'],
            'executionCount'           => (int) $config['execution_count'],
            'errorMessage'             => $config['error_message'],
            'secondsSinceLastExecution'=> $secondsSince !== null ? (int) $secondsSince : null,
            'currentSimulatedTime'     => $config['currentSimulatedTime'],
            'isRunning'                => $config['isRunning'],
            'isPaused'                 => $config['isPaused'],
            'isStopped'                => $config['isStopped'],
            'isHealthy'                => $config['isHealthy'],
        ];
    }

    private function hhmmToMinutes(string $hhmm): int
    {
        $parts = explode(':', $hhmm);
        return (int) $parts[0] * 60 + (int) ($parts[1] ?? 0);
    }

    private function minutesToHHMM(int $minutes): string
    {
        $h = intdiv($minutes, 60) % 24;
        $m = $minutes % 60;
        return sprintf('%02d:%02d', $h, $m);
    }

    /**
     * Current match on a pitch = the latest scheduled non-ATT match at or before the
     * working time.
     *
     * Mirrors live/create_cache_match.php::GetBestMatch exactly: only 'ATT' (upcoming)
     * matches are excluded; among 'ON' and 'END' matches whose Heure_match <= working
     * time, the one with the *latest* Heure_match wins. This is purely time-driven, so
     * when several matches are flagged ON at once (e.g. staff left a previous one ON),
     * the current game shown is the most recent scheduled one — matching event.php.
     *
     * $matches is ordered by Heure_match ASC.
     */
    private function getBestMatch(array $matches, string $pitch, int $workingMinutes): array
    {
        $bestIdx  = -1;
        $bestTime = -1;

        foreach ($matches as $i => $m) {
            if ($m['Terrain'] != $pitch || $m['Statut'] === 'ATT') {
                continue;
            }
            $t = $this->hhmmToMinutes($m['Heure_match']);
            if ($t <= $workingMinutes && $t > $bestTime) {
                $bestTime = $t;
                $bestIdx  = $i;
            }
        }

        if ($bestIdx === -1) {
            return ['id' => null, 'time' => null, 'num' => null];
        }

        return [
            'id'   => (int) $matches[$bestIdx]['Id'],
            'time' => $matches[$bestIdx]['Heure_match'],
            'num'  => $matches[$bestIdx]['Numero_ordre'] !== null ? (int) $matches[$bestIdx]['Numero_ordre'] : null,
        ];
    }

    /**
     * Next match on a pitch = the first ATT match whose Heure_match <= working time.
     *
     * Mirrors live/create_cache_match.php::GetNextMatch: rows are ordered by
     * Heure_match ASC, so the first ATT match already reached by the working time is
     * the one queued to be played next.
     */
    private function getNextMatch(array $matches, string $pitch, int $workingMinutes): array
    {
        foreach ($matches as $m) {
            if ($m['Terrain'] != $pitch || $m['Statut'] !== 'ATT') {
                continue;
            }
            if ($this->hhmmToMinutes($m['Heure_match']) <= $workingMinutes) {
                return [
                    'id'   => (int) $m['Id'],
                    'time' => $m['Heure_match'],
                    'num'  => $m['Numero_ordre'] !== null ? (int) $m['Numero_ordre'] : null,
                ];
            }
        }

        return ['id' => null, 'time' => null, 'num' => null];
    }
}
