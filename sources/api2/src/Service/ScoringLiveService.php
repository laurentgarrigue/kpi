<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * Canonical live match state (scoring refactoring, lot 1).
 *
 * Single write path for the live state of a match: scoring_live_state (score/period/status/
 * active source/tick), scoring_live_clock (N clocks: game, shotclock, penalties, break) and
 * scoring_live_event (match facts: goals, cards). Every mutation:
 *   1. runs inside ONE transaction,
 *   2. bumps scoring_live_state.tick,
 *   3. deposits the outbound Mercure message in scoring_outbox (same transaction — the
 *      transactional-outbox pattern: the state write is never blocked nor lost if the hub
 *      is slow or down; a worker drains the table, see plan lot 2).
 *
 * kp_* stays the reference model for results/reports and is only written by
 * consolidateToKp() when the match reaches END (plan §4.3).
 *
 * Docs: DOC/developer/reference/LIVE_MATCH_SCORING_REFACTORING_PROPOSALS.md (lot 1),
 *       DOC/specs/PAGE_SCORING.md §0.2–§0.5.
 */
class ScoringLiveService
{
    /** Blocks used as the last topic segment (plan §3.3). */
    private const CLOCK_BLOCKS = [
        'GAME' => 'clock',
        'SHOTCLOCK' => 'shotclock',
        'PENALTY' => 'penalty',
        'BREAK' => 'break',
    ];

    /** Per-request cache of event/pitch resolution, keyed by match id. */
    private array $topicContext = [];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    // ------------------------------------------------------------------
    // State lifecycle
    // ------------------------------------------------------------------

    /**
     * Make sure the match has a scoring_live_state row, seeding it from kp_match on first
     * touch (transition path: a match already started or corrected under the legacy flow
     * keeps its current status/period/score). Also seeds scoring_live_event from
     * kp_match_detail when the live table is still empty, so post-match corrections of
     * legacy-entered matches edit the same rows the consolidation will write back.
     *
     * Returns the current state row.
     *
     * @return array<string,mixed>
     */
    public function ensureState(int $matchId): array
    {
        $state = $this->connection->fetchAssociative(
            'SELECT * FROM scoring_live_state WHERE id_match = ?',
            [$matchId]
        );
        if ($state !== false) {
            return $state;
        }

        $kp = $this->connection->fetchAssociative(
            'SELECT Statut, Periode, ScoreDetailA, ScoreDetailB, Heure_fin FROM kp_match WHERE Id = ?',
            [$matchId]
        ) ?: [];

        $this->connection->executeStatement(
            'INSERT IGNORE INTO scoring_live_state
                (id_match, score_a, score_b, periode, statut, heure_fin)
             VALUES (?, ?, ?, ?, ?, ?)',
            [
                $matchId,
                (int) ($kp['ScoreDetailA'] ?? 0),
                (int) ($kp['ScoreDetailB'] ?? 0),
                ($kp['Periode'] ?? '') !== '' && $kp['Periode'] !== null ? $kp['Periode'] : 'M1',
                in_array($kp['Statut'] ?? 'ATT', ['ATT', 'ON', 'END'], true) ? $kp['Statut'] : 'ATT',
                $kp['Heure_fin'] ?? null,
            ]
        );

        // One-time seed of the facts from the legacy detail table (uid format is shared).
        $this->connection->executeStatement(
            "INSERT IGNORE INTO scoring_live_event
                (uid, id_match, kind, code, periode, temps, team, id_player, numero, motif, created_at)
             SELECT md.Id, md.Id_match,
                    CASE WHEN md.Id_evt_match = 'B' THEN 'GOAL' ELSE 'CARD' END,
                    md.Id_evt_match, md.Periode, md.Temps, md.Equipe_A_B,
                    md.Competiteur, md.Numero, md.motif, COALESCE(md.date_insert, NOW(3))
             FROM kp_match_detail md
             WHERE md.Id_match = ?
               AND NOT EXISTS (SELECT 1 FROM scoring_live_event e WHERE e.id_match = md.Id_match)",
            [$matchId]
        );

        return $this->connection->fetchAssociative(
            'SELECT * FROM scoring_live_state WHERE id_match = ?',
            [$matchId]
        ) ?: [];
    }

    /**
     * Guard for plan §4.1 (single active source): returns true when $source may write.
     * MANUAL is the console; the hardware relay will send HARDWARE, etc.
     */
    public function isSourceAllowed(array $state, string $source): bool
    {
        return ($state['active_source'] ?? 'MANUAL') === $source;
    }

    // ------------------------------------------------------------------
    // Mutations (each transactional, tick-bumping, outbox-depositing)
    // ------------------------------------------------------------------

    /**
     * Set a live parameter. Accepts the console vocabulary (kp column names, so the
     * endpoint payload is unchanged): Statut, Periode, ScoreDetailA/B, Heure_fin.
     * Returns the new tick. Statut → END triggers the kp_* consolidation.
     */
    public function setParam(int $matchId, string $param, ?string $value): int
    {
        return $this->connection->transactional(function () use ($matchId, $param, $value): int {
            $this->ensureState($matchId);

            $column = match ($param) {
                'Statut' => 'statut',
                'Periode' => 'periode',
                'ScoreDetailA' => 'score_a',
                'ScoreDetailB' => 'score_b',
                'Heure_fin' => 'heure_fin',
                default => throw new \InvalidArgumentException("Unsupported live param: $param"),
            };

            $this->connection->executeStatement(
                "UPDATE scoring_live_state SET $column = ? WHERE id_match = ?",
                [$value, $matchId]
            );

            $tick = $this->bumpTick($matchId);
            $state = $this->fetchState($matchId);
            $this->deposit($matchId, 'score', [
                'type' => 'state',
                'tick' => $tick,
                'statut' => $state['statut'],
                'periode' => $state['periode'],
                'scoreA' => (int) $state['score_a'],
                'scoreB' => (int) $state['score_b'],
            ]);

            if ($param === 'Statut' && $value === 'END') {
                $this->consolidateToKp($matchId);
            }

            return $tick;
        });
    }

    /**
     * Promote the active source of the match (plan §4.1). Timestamped; the existing state
     * is kept as-is. Returns the new tick.
     */
    public function promoteSource(int $matchId, string $source): int
    {
        if (!in_array($source, ['MANUAL', 'HARDWARE', 'SCORE_ONLY', 'IMPORT'], true)) {
            throw new \InvalidArgumentException("Unknown source: $source");
        }

        return $this->connection->transactional(function () use ($matchId, $source): int {
            $this->ensureState($matchId);
            $this->connection->executeStatement(
                'UPDATE scoring_live_state SET active_source = ?, promoted_at = NOW(3) WHERE id_match = ?',
                [$source, $matchId]
            );
            $tick = $this->bumpTick($matchId);
            $this->deposit($matchId, 'state', [
                'type' => 'source',
                'tick' => $tick,
                'activeSource' => $source,
            ]);

            return $tick;
        });
    }

    /**
     * Add / update / remove a match fact (goal, card). $fact carries the console payload:
     * uid, code (B/V/J/R/D), period, tpsJeu (already normalized HH:MM:SS), player, number,
     * team, reason. Returns the new tick.
     */
    public function writeEvent(int $matchId, string $action, array $fact): int
    {
        return $this->connection->transactional(function () use ($matchId, $action, $fact): int {
            $this->ensureState($matchId);

            if ($action === 'add') {
                $this->connection->executeStatement(
                    'INSERT INTO scoring_live_event
                        (uid, id_match, kind, code, periode, temps, team, id_player, numero, motif)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $fact['uid'], $matchId,
                        ($fact['code'] ?? '') === 'B' ? 'GOAL' : 'CARD',
                        $fact['code'], $fact['period'], $fact['temps'],
                        $fact['team'], $fact['player'], $fact['number'], $fact['reason'],
                    ]
                );
            } elseif ($action === 'update') {
                $this->connection->executeStatement(
                    'UPDATE scoring_live_event
                     SET kind = ?, code = ?, periode = ?, temps = ?, team = ?,
                         id_player = ?, numero = ?, motif = ?
                     WHERE uid = ? AND id_match = ?',
                    [
                        ($fact['code'] ?? '') === 'B' ? 'GOAL' : 'CARD',
                        $fact['code'], $fact['period'], $fact['temps'], $fact['team'],
                        $fact['player'], $fact['number'], $fact['reason'],
                        $fact['uid'], $matchId,
                    ]
                );
            } elseif ($action === 'remove') {
                if (!empty($fact['uid'])) {
                    $this->connection->executeStatement(
                        'DELETE FROM scoring_live_event WHERE uid = ? AND id_match = ?',
                        [$fact['uid'], $matchId]
                    );
                } else {
                    // Legacy fallback: delete the most recent matching fact.
                    $this->connection->executeStatement(
                        'DELETE FROM scoring_live_event
                         WHERE id_match = ? AND periode = ? AND id_player = ? AND code = ?
                         ORDER BY created_at DESC LIMIT 1',
                        [$matchId, $fact['period'], $fact['player'], $fact['code']]
                    );
                }
            } else {
                throw new \InvalidArgumentException("Unknown event action: $action");
            }

            $tick = $this->bumpTick($matchId);
            $this->deposit($matchId, 'fact', [
                'type' => 'fact',
                'tick' => $tick,
                'action' => $action,
                'fact' => [
                    'uid' => $fact['uid'] ?? null,
                    'code' => $fact['code'] ?? null,
                    'period' => $fact['period'] ?? null,
                    'tpsJeu' => $fact['tpsJeu'] ?? null,
                    'team' => $fact['team'] ?? null,
                    'player' => $fact['player'] ?? null,
                    'number' => $fact['number'] ?? null,
                    'reason' => $fact['reason'] ?? null,
                ],
            ]);

            return $tick;
        });
    }

    /**
     * Upsert one clock of the match (4-value model, plan §3.1). $clock keys:
     * kind (GAME/SHOTCLOCK/PENALTY/BREAK), team (''|A|B), slot (0|1|2), initMs, elapsedMs,
     * running (bool), startedAt (client timestamp, plan §4.2 — server time used as fallback),
     * playerId, cardCode. Returns the new tick.
     */
    public function setClock(int $matchId, array $clock): int
    {
        $kind = $clock['kind'] ?? 'GAME';
        if (!isset(self::CLOCK_BLOCKS[$kind])) {
            throw new \InvalidArgumentException("Unknown clock kind: $kind");
        }

        return $this->connection->transactional(function () use ($matchId, $clock, $kind): int {
            $this->ensureState($matchId);

            $team = (string) ($clock['team'] ?? '');
            $slot = (int) ($clock['slot'] ?? 0);
            $running = !empty($clock['running']);

            $this->connection->executeStatement(
                'INSERT INTO scoring_live_clock
                    (id, id_match, kind, team, slot, id_player, card_code,
                     init_ms, elapsed_ms, started_at, running)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    id_player = VALUES(id_player), card_code = VALUES(card_code),
                    init_ms = VALUES(init_ms), elapsed_ms = VALUES(elapsed_ms),
                    started_at = VALUES(started_at), running = VALUES(running)',
                [
                    $clock['id'] ?? $this->uuid(),
                    $matchId, $kind, $team, $slot,
                    $clock['playerId'] ?? null,
                    $clock['cardCode'] ?? null,
                    (int) ($clock['initMs'] ?? 0),
                    (int) ($clock['elapsedMs'] ?? 0),
                    $running ? ($clock['startedAt'] ?? date('Y-m-d H:i:s.v')) : null,
                    $running ? 1 : 0,
                ]
            );

            return $this->depositClock($matchId, $kind, $team, $slot);
        });
    }

    /**
     * Remove one clock (RAZ of the game clock, end of a penalty…). Returns the new tick.
     */
    public function removeClock(int $matchId, string $kind, string $team = '', int $slot = 0): int
    {
        return $this->connection->transactional(function () use ($matchId, $kind, $team, $slot): int {
            $this->ensureState($matchId);
            $this->connection->executeStatement(
                'DELETE FROM scoring_live_clock WHERE id_match = ? AND kind = ? AND team = ? AND slot = ?',
                [$matchId, $kind, $team, $slot]
            );

            return $this->depositClock($matchId, $kind, $team, $slot);
        });
    }

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Full live snapshot of a match: state + clocks + facts (enriched with player names).
     * Used by GET /admin/scoring/state (console resume, supervision) and, later, by the
     * overlay page (public read path, lot 4).
     *
     * @return array<string,mixed>|null null when the match has no live state yet
     */
    public function getState(int $matchId): ?array
    {
        $state = $this->connection->fetchAssociative(
            'SELECT * FROM scoring_live_state WHERE id_match = ?',
            [$matchId]
        );
        if ($state === false) {
            return null;
        }

        $clocks = $this->connection->fetchAllAssociative(
            'SELECT id, kind, team, slot, id_player playerId, card_code cardCode,
                    init_ms initMs, elapsed_ms elapsedMs, started_at startedAt, running
             FROM scoring_live_clock WHERE id_match = ?
             ORDER BY kind, team, slot',
            [$matchId]
        );

        $events = $this->connection->fetchAllAssociative(
            "SELECT e.uid, e.kind, e.code, e.periode period,
                    TIME_FORMAT(e.temps, '%i:%s') tpsJeu, e.team,
                    e.id_player player, e.numero number, e.motif reason,
                    l.Nom nom, l.Prenom prenom
             FROM scoring_live_event e
             LEFT OUTER JOIN kp_licence l ON (e.id_player = l.Matric)
             WHERE e.id_match = ?
             ORDER BY e.periode DESC, e.temps ASC, e.created_at ASC",
            [$matchId]
        );

        return [
            'matchId' => $matchId,
            'tick' => (int) $state['tick'],
            'updatedAt' => $state['updated_at'],
            'statut' => $state['statut'],
            'periode' => $state['periode'],
            'scoreA' => (int) $state['score_a'],
            'scoreB' => (int) $state['score_b'],
            'heureFin' => $state['heure_fin'],
            'activeSource' => $state['active_source'],
            'promotedAt' => $state['promoted_at'],
            'clocks' => array_map(static function (array $c): array {
                $c['initMs'] = (int) $c['initMs'];
                $c['elapsedMs'] = (int) $c['elapsedMs'];
                $c['slot'] = (int) $c['slot'];
                $c['running'] = (bool) $c['running'];
                return $c;
            }, $clocks),
            'events' => $events,
        ];
    }

    // ------------------------------------------------------------------
    // Consolidation (plan §4.3 — the ONLY moment scoring writes kp_*)
    // ------------------------------------------------------------------

    /**
     * Copy the live state back to kp_* — exactly what the legacy closure already does
     * (score, status, period, end time) plus the facts (kp_match_detail is rebuilt from
     * scoring_live_event so reports/PDF/rankings see the corrected timeline).
     * Runs inside the caller's transaction.
     */
    public function consolidateToKp(int $matchId): void
    {
        $state = $this->fetchState($matchId);
        if ($state === null) {
            return;
        }

        $this->connection->executeStatement(
            "UPDATE kp_match
             SET Statut = ?, Periode = ?, ScoreDetailA = ?, ScoreDetailB = ?,
                 Heure_fin = COALESCE(?, Heure_fin)
             WHERE Id = ? AND Validation != 'O'",
            [
                $state['statut'], $state['periode'],
                (int) $state['score_a'], (int) $state['score_b'],
                $state['heure_fin'], $matchId,
            ]
        );

        // Rebuild the detail rows from the live facts (uid format is shared with
        // kp_match_detail.Id, so consolidated rows keep their identity).
        $this->connection->executeStatement(
            'DELETE FROM kp_match_detail WHERE Id_match = ?',
            [$matchId]
        );
        $this->connection->executeStatement(
            'INSERT INTO kp_match_detail
                (Id, Id_match, Periode, Temps, Id_evt_match, Competiteur, Numero, Equipe_A_B, motif)
             SELECT uid, id_match, periode, temps, code, id_player, numero, team, motif
             FROM scoring_live_event WHERE id_match = ?',
            [$matchId]
        );
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    private function fetchState(int $matchId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM scoring_live_state WHERE id_match = ?',
            [$matchId]
        );

        return $row === false ? null : $row;
    }

    /** Increment and return the match tick (state row must exist). */
    private function bumpTick(int $matchId): int
    {
        $this->connection->executeStatement(
            'UPDATE scoring_live_state SET tick = tick + 1 WHERE id_match = ?',
            [$matchId]
        );

        return (int) $this->connection->fetchOne(
            'SELECT tick FROM scoring_live_state WHERE id_match = ?',
            [$matchId]
        );
    }

    /** Bump tick + deposit the full clock row (or its removal) on the matching topic. */
    private function depositClock(int $matchId, string $kind, string $team, int $slot): int
    {
        $tick = $this->bumpTick($matchId);
        $row = $this->connection->fetchAssociative(
            'SELECT id, kind, team, slot, id_player playerId, card_code cardCode,
                    init_ms initMs, elapsed_ms elapsedMs, started_at startedAt, running
             FROM scoring_live_clock
             WHERE id_match = ? AND kind = ? AND team = ? AND slot = ?',
            [$matchId, $kind, $team, $slot]
        );

        $this->deposit($matchId, self::CLOCK_BLOCKS[$kind], [
            'type' => 'clock',
            'tick' => $tick,
            'kind' => $kind,
            'team' => $team,
            'slot' => $slot,
            'clock' => $row === false ? null : [
                'id' => $row['id'],
                'playerId' => $row['playerId'],
                'cardCode' => $row['cardCode'],
                'initMs' => (int) $row['initMs'],
                'elapsedMs' => (int) $row['elapsedMs'],
                'startedAt' => $row['startedAt'],
                'running' => (bool) $row['running'],
            ],
        ]);

        return $tick;
    }

    /**
     * Deposit an outbox row addressed to the event/pitch/block topic (plan §3.3).
     * Falls back to a match-scoped topic when the match is not attached to a KPI event
     * or has no numeric pitch.
     */
    private function deposit(int $matchId, string $block, array $payload): void
    {
        $ctx = $this->resolveTopicContext($matchId);
        $topic = $ctx === null
            ? "/scoring/match/{$matchId}/{$block}"
            : "/scoring/event/{$ctx['event']}/pitch/{$ctx['pitch']}/{$block}";

        $payload['matchId'] = $matchId;

        $this->connection->executeStatement(
            'INSERT INTO scoring_outbox (id_match, topic, payload, tick) VALUES (?, ?, ?, ?)',
            [$matchId, $topic, json_encode($payload, JSON_UNESCAPED_UNICODE), (int) ($payload['tick'] ?? 0)]
        );
    }

    /**
     * Resolve the KPI event + pitch of a match (match → journée → kp_evenement_journee,
     * pitch = kp_match.Terrain). Cached per request. Null when not resolvable.
     *
     * @return array{event:int,pitch:string}|null
     */
    private function resolveTopicContext(int $matchId): ?array
    {
        if (array_key_exists($matchId, $this->topicContext)) {
            return $this->topicContext[$matchId];
        }

        $row = $this->connection->fetchAssociative(
            'SELECT ej.Id_evenement event, m.Terrain pitch
             FROM kp_match m
             LEFT JOIN kp_evenement_journee ej ON ej.Id_journee = m.Id_journee
             WHERE m.Id = ?
             LIMIT 1',
            [$matchId]
        );

        $ctx = null;
        if ($row !== false && $row['event'] !== null && $row['pitch'] !== null && $row['pitch'] !== '') {
            $ctx = ['event' => (int) $row['event'], 'pitch' => (string) $row['pitch']];
        }

        return $this->topicContext[$matchId] = $ctx;
    }

    /** RFC 4122 v4 UUID (emitter-side ids are preferred; this is the server fallback). */
    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
