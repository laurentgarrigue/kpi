<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * "Programme du terrain": which match is being played on a pitch, which one comes next,
 * and with which display settings — the data the video overlay needs to run on its own
 * (PAGE_INCRUSTATION.md §6/§7, plan lot 4).
 *
 * Two things matter here:
 *
 *  1. **The selection logic stays where it already is.** The current/next match is the
 *     same question the Event Cache Manager already answers (EventCacheService::
 *     getBestMatch/getNextMatch, ported from the legacy CacheMatch). What changes is the
 *     *publication*: instead of a JSON file the overlay must poll, the program is served
 *     by an endpoint and pushed on Mercure whenever it changes.
 *
 *  2. **Settings resolve by specificity**: server defaults → event row → pitch row. A NULL
 *     column means "inherit", never "zero" — so an event can set one delay without having
 *     to restate the others.
 *
 * The publication side (outbox row on change) lives in publishIfChanged(), called by the
 * worker and by the scoring console when a status change makes the program move.
 */
class ScoringProgramService
{
    /**
     * Server defaults of the display chaining (seconds), spec §7.
     * They are the fallback of every event/pitch override.
     */
    public const DEFAULTS = [
        // Half-time score shown 5 s after the period ends, until the next period starts
        'halftimeScoreDelay' => 5,
        // Final score shown 5 s after the last period ends (or as soon as status = END)
        'finalScoreDelay' => 5,
        'finalScoreDuration' => 120,
        // Then the presentation of the next match
        'nextGameDelay' => 10,
        'nextGameDuration' => 0, // 0 = until kick-off
        // Alpha for OBS by default; 'magenta' or any CSS colour for chroma keying
        'background' => 'transparent',
        'styleId' => 'default',
    ];

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Full program of a pitch: current match, next match and resolved display settings.
     *
     * @return array<string,mixed>
     */
    public function getProgram(int $idEvent, string $pitch): array
    {
        $current = $this->currentMatch($idEvent, $pitch);
        $next = $this->nextMatch($idEvent, $pitch, $current['id'] ?? null);

        return [
            'event' => $idEvent,
            'pitch' => $pitch,
            'current' => $current,
            'next' => $next,
            'settings' => $this->resolveSettings($idEvent, $pitch),
            'topicBase' => "/scoring/event/{$idEvent}/pitch/{$pitch}",
        ];
    }

    /**
     * Display settings for a pitch: defaults, overridden by the event row, then by the
     * pitch row. Only non-NULL columns override (NULL = inherit).
     *
     * @return array<string,mixed>
     */
    public function resolveSettings(int $idEvent, string $pitch): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT * FROM scoring_display_settings
             WHERE id_event = ? AND (pitch IS NULL OR pitch = ?)
             ORDER BY pitch IS NULL DESC', // event row first, pitch row last (wins)
            [$idEvent, $pitch]
        );

        $settings = self::DEFAULTS;
        $map = [
            'halftime_score_delay' => 'halftimeScoreDelay',
            'final_score_delay' => 'finalScoreDelay',
            'final_score_duration' => 'finalScoreDuration',
            'next_game_delay' => 'nextGameDelay',
            'next_game_duration' => 'nextGameDuration',
            'background' => 'background',
            'style_id' => 'styleId',
        ];

        foreach ($rows as $row) {
            foreach ($map as $column => $key) {
                if ($row[$column] !== null && $row[$column] !== '') {
                    $settings[$key] = is_numeric($row[$column]) ? (int) $row[$column] : $row[$column];
                }
            }
        }

        return $settings;
    }

    /**
     * Current match of a pitch. Live status wins (a match flagged ON is being played,
     * whatever the schedule says); otherwise the most recent match whose kick-off time
     * has passed today — same rule as the legacy cache generator.
     *
     * @return array<string,mixed>|null
     */
    public function currentMatch(int $idEvent, string $pitch): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT m.Id, m.Numero_ordre, m.Heure_match, m.Date_match, m.Statut,
                    m.Terrain, m.Type,
                    COALESCE(s.statut, m.Statut) live_statut,
                    ea.Libelle equipeA, eb.Libelle equipeB
             FROM kp_match m
             INNER JOIN kp_evenement_journee ej ON ej.Id_journee = m.Id_journee
             LEFT JOIN scoring_live_state s ON s.id_match = m.Id
             LEFT JOIN kp_competition_equipe ea ON ea.Id = m.Id_equipeA
             LEFT JOIN kp_competition_equipe eb ON eb.Id = m.Id_equipeB
             WHERE ej.Id_evenement = ? AND m.Terrain = ? AND m.Publication = 'O'
               AND m.Date_match = CURDATE()
             ORDER BY
               -- a match actually running comes first, then the latest already started
               (COALESCE(s.statut, m.Statut) = 'ON') DESC,
               (m.Heure_match <= DATE_FORMAT(NOW(), '%H:%i')) DESC,
               m.Heure_match DESC
             LIMIT 1",
            [$idEvent, $pitch]
        );

        return $row === false ? null : $this->formatMatch($row);
    }

    /**
     * Next match of a pitch: the first one still waiting (ATT) after the current one.
     *
     * @return array<string,mixed>|null
     */
    public function nextMatch(int $idEvent, string $pitch, ?int $currentId): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT m.Id, m.Numero_ordre, m.Heure_match, m.Date_match, m.Statut,
                    m.Terrain, m.Type,
                    COALESCE(s.statut, m.Statut) live_statut,
                    ea.Libelle equipeA, eb.Libelle equipeB
             FROM kp_match m
             INNER JOIN kp_evenement_journee ej ON ej.Id_journee = m.Id_journee
             LEFT JOIN scoring_live_state s ON s.id_match = m.Id
             LEFT JOIN kp_competition_equipe ea ON ea.Id = m.Id_equipeA
             LEFT JOIN kp_competition_equipe eb ON eb.Id = m.Id_equipeB
             WHERE ej.Id_evenement = ? AND m.Terrain = ? AND m.Publication = 'O'
               AND m.Date_match = CURDATE()
               AND COALESCE(s.statut, m.Statut) = 'ATT'
               AND (? IS NULL OR m.Id <> ?)
             ORDER BY m.Heure_match ASC, m.Numero_ordre ASC
             LIMIT 1",
            [$idEvent, $pitch, $currentId, $currentId]
        );

        return $row === false ? null : $this->formatMatch($row);
    }

    /**
     * Publish the program on Mercure **only when it actually changed** (outbox row, drained
     * by the worker like every other scoring message). Called by the worker on its regular
     * pass and by the console right after a status change, so the overlay switches matches
     * without waiting for the next worker tick — the gain over the legacy polling.
     *
     * @return bool true when a change was published
     */
    public function publishIfChanged(int $idEvent, string $pitch): bool
    {
        $program = $this->getProgram($idEvent, $pitch);
        $topic = "/scoring/event/{$idEvent}/pitch/{$pitch}/program";

        // Signature of what the overlay actually reacts to (ids, status, settings).
        $signature = md5(json_encode([
            $program['current']['id'] ?? null,
            $program['current']['statut'] ?? null,
            $program['next']['id'] ?? null,
            $program['settings'],
        ]));

        $lastSignature = $this->connection->fetchOne(
            'SELECT JSON_UNQUOTE(JSON_EXTRACT(payload, "$.signature"))
             FROM scoring_outbox WHERE topic = ? ORDER BY id DESC LIMIT 1',
            [$topic]
        );

        if ($lastSignature === $signature) {
            return false;
        }

        $this->connection->executeStatement(
            'INSERT INTO scoring_outbox (id_match, topic, payload, tick) VALUES (?, ?, ?, ?)',
            [
                $program['current']['id'] ?? 0,
                $topic,
                json_encode(['type' => 'program', 'signature' => $signature] + $program, JSON_UNESCAPED_UNICODE),
                0,
            ]
        );

        return true;
    }

    /**
     * Resolve the event + pitch a match belongs to — lets the console trigger a program
     * refresh from a match id alone.
     *
     * @return array{event:int,pitch:string}|null
     */
    public function locateMatch(int $matchId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT ej.Id_evenement event, m.Terrain pitch
             FROM kp_match m
             INNER JOIN kp_evenement_journee ej ON ej.Id_journee = m.Id_journee
             WHERE m.Id = ? LIMIT 1',
            [$matchId]
        );

        if ($row === false || $row['event'] === null || ($row['pitch'] ?? '') === '') {
            return null;
        }

        return ['event' => (int) $row['event'], 'pitch' => (string) $row['pitch']];
    }

    /** @param array<string,mixed> $row */
    private function formatMatch(array $row): array
    {
        return [
            'id' => (int) $row['Id'],
            'numero' => $row['Numero_ordre'] !== null ? (int) $row['Numero_ordre'] : null,
            'heure' => $row['Heure_match'],
            'date' => $row['Date_match'],
            'statut' => $row['live_statut'],
            'type' => $row['Type'],
            'equipeA' => $row['equipeA'],
            'equipeB' => $row['equipeB'],
        ];
    }
}
