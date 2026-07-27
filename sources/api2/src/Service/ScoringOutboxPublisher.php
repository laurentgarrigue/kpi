<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Drains scoring_outbox and publishes the pending messages to the Mercure hub
 * (scoring refactoring, lot 2 — the read half of the transactional-outbox pattern).
 *
 * Guarantees:
 *  - strict ordering: rows are published by ascending id, and draining STOPS at the
 *    first failure so a later message can never overtake a failed earlier one;
 *  - at-least-once delivery: a row is marked published only after the hub accepted it.
 *    A crash between publish and mark republishes the row on the next pass — subscribers
 *    deduplicate with the `tick` carried by every payload;
 *  - the hub being slow or down never blocks or loses state writes: rows simply pile up
 *    (indexed on published_at) and are replayed when the hub comes back.
 *
 * The publisher is invoked by the existing app:event-cache-worker daemon (plan lot 2:
 * no second worker model, no Messenger). Topic and payload were fixed at write time by
 * ScoringLiveService — this class never builds addressing or business content itself.
 */
class ScoringOutboxPublisher
{
    public function __construct(
        private readonly Connection $connection,
        private readonly HubInterface $hub,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Publish pending rows (published_at IS NULL) in id order, at most $maxRows in one
     * call. Returns the number of rows actually published. Throws after having marked
     * the rows already accepted by the hub, so the caller can log and retry later
     * without breaking ordering.
     */
    public function drain(int $maxRows = 200): int
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, topic, payload FROM scoring_outbox
             WHERE published_at IS NULL
             ORDER BY id ASC
             LIMIT ' . max(1, $maxRows)
        );

        $published = 0;
        foreach ($rows as $row) {
            $payload = (string) $row['payload'];
            $type = null;
            $decoded = json_decode($payload, true);
            if (is_array($decoded) && isset($decoded['type']) && is_string($decoded['type'])) {
                $type = $decoded['type']; // SSE `event:` field — lets EventSource listeners filter
            }

            try {
                $this->hub->publish(new Update(
                    (string) $row['topic'],
                    $payload,
                    private: false,
                    id: 'urn:kpi:scoring:' . $row['id'],
                    type: $type,
                ));
            } catch (\Throwable $e) {
                // Stop here: marking nothing for this row keeps it first in line for the
                // next pass, and not publishing the following rows preserves ordering.
                $this->logger?->warning('scoring_outbox drain stopped on row {id}: {error}', [
                    'id' => $row['id'],
                    'error' => $e->getMessage(),
                ]);

                throw $e;
            }

            $this->connection->executeStatement(
                'UPDATE scoring_outbox SET published_at = NOW(3) WHERE id = ?',
                [$row['id']]
            );
            $published++;
        }

        return $published;
    }

    /** True when at least one row is waiting to be published. */
    public function hasPending(): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT EXISTS(SELECT 1 FROM scoring_outbox WHERE published_at IS NULL)'
        );
    }

    /**
     * Delete old published rows so the table stays small (the Mercure history — bolt
     * transport — is the replay source for subscribers, not this table). Bounded DELETE
     * to keep each pass cheap; call it at a slow cadence (the worker does, ~every 10 min).
     * Returns the number of deleted rows.
     */
    public function prune(int $olderThanMinutes = 60, int $maxRows = 1000): int
    {
        return (int) $this->connection->executeStatement(
            'DELETE FROM scoring_outbox
             WHERE published_at IS NOT NULL
               AND published_at < NOW(3) - INTERVAL ' . max(1, $olderThanMinutes) . ' MINUTE
             LIMIT ' . max(1, $maxRows)
        );
    }
}
