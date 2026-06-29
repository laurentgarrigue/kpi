<?php

namespace App\Trait;

/**
 * Trait to guard write operations against a locked / ended competition.
 *
 * A competition is read-only when it is locked (Verrou = 'O') or when its
 * status is END. Moving a competition to END also sets the lock, so checking
 * either covers the case, but we test both to stay robust against legacy data
 * where a competition could be END without the lock having been set.
 *
 * Controllers using this trait must have a `$this->connection`
 * (Doctrine DBAL Connection) property.
 */
trait CompetitionLockTrait
{
    /**
     * Whether the given competition is read-only (locked or ended).
     *
     * Returns false when the competition cannot be found, leaving the caller's
     * own "not found" handling untouched.
     */
    private function isCompetitionReadOnly(string $code, string $season): bool
    {
        $sql = "SELECT Statut, Verrou FROM kp_competition WHERE Code = ? AND Code_saison = ?";
        $row = $this->connection->prepare($sql)->executeQuery([$code, $season])->fetchAssociative();

        if (!$row) {
            return false;
        }

        return $row['Verrou'] === 'O' || $row['Statut'] === 'END';
    }

    /**
     * Filter a list of gameday IDs, keeping only those whose competition is
     * still editable (not locked, not ended). Used by bulk operations so that
     * gamedays of an ended competition are silently skipped instead of failing
     * the whole batch.
     *
     * @param int[] $ids
     * @return int[] the subset of $ids that may be written to
     */
    private function filterEditableGamedayIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT j.Id
                FROM kp_journee j
                INNER JOIN kp_competition c
                    ON c.Code = j.Code_competition AND c.Code_saison = j.Code_saison
                WHERE j.Id IN ($placeholders)
                  AND (c.Verrou = 'O' OR c.Statut = 'END')";

        $lockedIds = $this->connection->prepare($sql)
            ->executeQuery(array_values($ids))
            ->fetchFirstColumn();
        $lockedIds = array_map('intval', $lockedIds);

        return array_values(array_filter($ids, fn ($id) => !in_array($id, $lockedIds, true)));
    }

    /**
     * The active season code ('A' state), or null if none is set.
     */
    private function getActiveSeasonCode(): ?string
    {
        $code = $this->connection->fetchOne("SELECT Code FROM kp_saison WHERE Etat = 'A' LIMIT 1");
        return $code === false ? null : (string) $code;
    }

    /**
     * Whether $season is strictly older than the active season.
     *
     * Used to enforce the rule "profiles > 2 cannot create/modify/delete
     * anything in seasons prior to the active one" (PROMPTS.md). Season codes
     * are comparable as strings (e.g. "2024-2025" < "2025-2026").
     */
    private function isPastSeason(?string $season): bool
    {
        if (empty($season)) {
            return false;
        }
        $active = $this->getActiveSeasonCode();
        return $active !== null && $season < $active;
    }
}
