<?php

namespace App\Trait;

/**
 * Trait to guard write operations against an ended competition.
 *
 * A competition is read-only for its structural data (gamedays, teams, …) only
 * when its status is END. The lock flag (Verrou = 'O') is NOT a read-only
 * signal here: it exclusively freezes the presence sheets and is enforced
 * independently by the presence controller. Structural edits (e.g. editing a
 * gameday phase inline) must stay possible on a merely locked competition.
 *
 * Controllers using this trait must have a `$this->connection`
 * (Doctrine DBAL Connection) property.
 */
trait CompetitionLockTrait
{
    /**
     * Whether the given competition is read-only, i.e. its status is END.
     *
     * The lock flag (Verrou) is deliberately ignored: it only guards presence
     * sheets, not structural competition data.
     *
     * Returns false when the competition cannot be found, leaving the caller's
     * own "not found" handling untouched.
     */
    private function isCompetitionReadOnly(string $code, string $season): bool
    {
        $sql = "SELECT Statut FROM kp_competition WHERE Code = ? AND Code_saison = ?";
        $row = $this->connection->prepare($sql)->executeQuery([$code, $season])->fetchAssociative();

        if (!$row) {
            return false;
        }

        return $row['Statut'] === 'END';
    }

    /**
     * Filter a list of gameday IDs, keeping only those whose competition is
     * still editable (status not END). Used by bulk operations so that gamedays
     * of an ended competition are silently skipped instead of failing the whole
     * batch. The lock flag (Verrou) is ignored: it only guards presence sheets.
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
                  AND c.Statut = 'END'";

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
