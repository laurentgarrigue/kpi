<?php

namespace App\Scoring;

/**
 * Pure game rules of the scoring refactoring (plan lot 1.2) — no database, no network,
 * no clock: every function is deterministic so the whole file is unit-testable.
 *
 * Covers (see DOC/specs/PAGE_SCORING.md):
 *  - period sequencing, unbounded overtimes P{n} and golden goal (§0.6, §7.5);
 *  - card progression green → yellow → red, black ejection card anytime (§7.4, 2027 rules);
 *  - penalty slots (≤ 2 concurrent per team), early lift on conceded goal, who returns (§7.4);
 *  - shotclock 3-command state machine (§6.5: start/reset 60, start/reset 40, stop).
 *
 * Tests: sources/api2/tests/Scoring/scoring_rules_test.php (standalone, `php <file>`).
 */
final class ScoringRules
{
    /** Card ranks for the progression rule (§7.4). D (black, ejection) is out of the ladder. */
    private const CARD_RANK = ['V' => 1, 'J' => 2, 'R' => 3];

    /** Cards whose sanctioned player never comes back on the water (§7.4, 2026-07-29). */
    private const NO_RETURN_CARDS = ['R', 'D'];

    /** Cards whose penalty is lifted early when the short-handed team concedes a goal. */
    private const LIFTABLE_CARDS = ['V', 'J'];

    public const SHOTCLOCK_IDLE = 'IDLE';           // displayed "--", waiting for a start
    public const SHOTCLOCK_RUNNING = 'RUNNING';
    public const SHOTCLOCK_SUSPENDED = 'SUSPENDED'; // auto-paused because the game clock stopped

    // ------------------------------------------------------------------
    // Periods (§0.6 / §7.5)
    // ------------------------------------------------------------------

    /** True for any overtime period: P1, P2, … P{n} (unbounded). */
    public static function isOvertime(string $period): bool
    {
        return preg_match('/^P\d+$/', $period) === 1;
    }

    /** Overtime index (P3 → 3), or null when not an overtime. */
    public static function overtimeIndex(string $period): ?int
    {
        return self::isOvertime($period) ? (int) substr($period, 1) : null;
    }

    /**
     * Next period for the "advance" button (§7.1):
     *  - type C (classement): M1 → M2 → null (a draw is allowed, no overtime);
     *  - type E (élimination): M1 → M2 → P1 → P2 → … (unbounded while the score is level),
     *    then TB only when the competition enables it (§7.5) — golden goal is the rule.
     * Returns null when no further period is possible.
     */
    public static function nextPeriod(string $type, string $period, bool $scoreLevel, bool $shootoutEnabled = false): ?string
    {
        if ($period === 'M1') {
            return 'M2';
        }
        if ($type !== 'E' || !$scoreLevel) {
            return null; // regulation ended with a winner, or draws are allowed
        }
        if ($period === 'M2') {
            return 'P1';
        }
        if (self::isOvertime($period)) {
            // Unbounded series; TB (when enabled) is an alternative exit, offered alongside.
            return 'P' . (self::overtimeIndex($period) + 1);
        }
        if ($period === 'TB') {
            return null;
        }

        return $shootoutEnabled ? 'TB' : null;
    }

    /**
     * Golden goal (§7.5): in an elimination match, the first goal scored during any
     * overtime ends the match immediately.
     */
    public static function goalEndsMatch(string $type, string $period): bool
    {
        return $type === 'E' && self::isOvertime($period);
    }

    /**
     * Duration in seconds of a period, resolved from a ScoringConfig-shaped array
     * (§6.2: M1/M2/TB explicit, one shared duration for every P{n}).
     *
     * @param array{M1?:int,M2?:int,P?:int,TB?:int} $periodDurations
     */
    public static function periodDuration(string $period, array $periodDurations): int
    {
        if (self::isOvertime($period)) {
            return $periodDurations['P'] ?? 300; // 5 min — ICF & FFCK (§0.9)
        }

        return $periodDurations[$period] ?? match ($period) {
            'M1', 'M2' => 600,
            'TB' => 180,
            default => 600,
        };
    }

    /**
     * Inter-period break duration in seconds (§4.10 of the plan / spec §7.5):
     * 3 min before M2, 3 min before the first overtime, 1 min between overtimes.
     * Null when no break clock applies (e.g. before M1, before TB).
     *
     * @param array{halftime?:int,beforeOvertime?:int,betweenOvertimes?:int} $breakDurations
     */
    public static function breakDurationBefore(string $nextPeriod, array $breakDurations = []): ?int
    {
        if ($nextPeriod === 'M2') {
            return $breakDurations['halftime'] ?? 180;
        }
        if ($nextPeriod === 'P1') {
            return $breakDurations['beforeOvertime'] ?? 180;
        }
        if (self::isOvertime($nextPeriod)) {
            return $breakDurations['betweenOvertimes'] ?? 60;
        }

        return null;
    }

    // ------------------------------------------------------------------
    // Cards (§7.4 — 2027 rules)
    // ------------------------------------------------------------------

    /**
     * Card progression rule: order V → J → R; a player cannot receive a 2nd/3rd card
     * identical or lower than his previous one — but nothing forces starting at V (a first
     * J or R is legal). The black ejection card (D) is applicable at any time; after a D
     * (or an R, which also ends the player's match) no further card is possible.
     *
     * @param string[] $previousCards codes already given to THIS player (V/J/R/D)
     * @return true|string true when allowed, else an i18n-able violation key
     */
    public static function validateCardProgression(array $previousCards, string $newCard): true|string
    {
        if (!isset(self::CARD_RANK[$newCard]) && $newCard !== 'D') {
            return 'unknown_card';
        }
        if (array_intersect($previousCards, self::NO_RETURN_CARDS) !== []) {
            return 'player_already_out'; // R or D already: the player's match is over
        }
        if ($newCard === 'D') {
            return true; // ejection card: any time, whatever the progression
        }

        $maxPrevious = 0;
        foreach ($previousCards as $code) {
            $maxPrevious = max($maxPrevious, self::CARD_RANK[$code] ?? 0);
        }

        return self::CARD_RANK[$newCard] > $maxPrevious ? true : 'card_not_higher';
    }

    /** §0.9: whether the sanctioned player himself returns at the end of the penalty. */
    public static function playerReturnsAfterPenalty(string $cardCode): bool
    {
        return !in_array($cardCode, self::NO_RETURN_CARDS, true);
    }

    /**
     * §7.4 (correction 2026-07-29): the black ejection card (D) carries NO 2-minute
     * penalty at all — immediate and definitive exclusion, no replacement until the end
     * of the match (the team finishes short-handed). Only V/J/R start a penalty clock.
     */
    public static function cardCreatesPenaltyClock(string $cardCode): bool
    {
        return in_array($cardCode, ['V', 'J', 'R'], true);
    }

    /**
     * §7.4 (correction 2026-07-29): early lift on a conceded goal applies to V/J only
     * (the player returns). A red-card (R) penalty runs its FULL 2 minutes whatever
     * happens — even if one or several goals are conceded — and the replacement is only
     * allowed at its end.
     */
    public static function penaltyLiftableOnGoal(string $cardCode): bool
    {
        return in_array($cardCode, self::LIFTABLE_CARDS, true);
    }

    // ------------------------------------------------------------------
    // Penalties (§7.4 — ≤ 2 concurrent per team, slots 1|2)
    // ------------------------------------------------------------------

    /**
     * First free penalty slot for a team, or null when both are busy (a team can never
     * drop below 3 players on the water → at most 2 concurrent exclusions).
     *
     * @param int[] $busySlots slots currently in use for the team
     */
    public static function freePenaltySlot(array $busySlots): ?int
    {
        foreach ([1, 2] as $slot) {
            if (!in_array($slot, $busySlots, true)) {
                return $slot;
            }
        }

        return null;
    }

    /**
     * Which penalty is lifted when the short-handed team concedes a goal (§7.4,
     * correction 2026-07-29): the OLDEST among the LIFTABLE ones only (V/J — the player
     * returns). R penalties are never lifted early (full 2 minutes, replacement at the
     * end); D never has a clock (see cardCreatesPenaltyClock()).
     *
     * @param array<int,array{slot:int,startedAt:string,cardCode:string}> $penalties active penalties of the team
     * @return int|null slot to lift, null when none is liftable
     */
    public static function penaltySlotToLift(array $penalties): ?int
    {
        $liftable = array_values(array_filter(
            $penalties,
            static fn (array $p): bool => self::penaltyLiftableOnGoal($p['cardCode'])
        ));
        if ($liftable === []) {
            return null;
        }
        usort($liftable, static fn (array $a, array $b): int => strcmp($a['startedAt'], $b['startedAt']));

        return $liftable[0]['slot'];
    }

    // ------------------------------------------------------------------
    // Shotclock — 3 commands, 3 states (§6.5, decision 2026-07-27)
    // ------------------------------------------------------------------

    /**
     * Shotclock transition function. States: IDLE ("--"), RUNNING, SUSPENDED (auto-paused
     * by the stopped game clock). Commands:
     *  - start60 / start40 : load 60 s / 40 s AND run — the start IS a reset, legal from
     *    any state, independent from the game clock;
     *  - stop              : back to IDLE (this is NOT a pause — the countdown is discarded);
     *  - gameClockStopped  : RUNNING → SUSPENDED (the only "pause", and it is automatic);
     *  - gameClockStarted  : SUSPENDED → RUNNING.
     *
     * @return string the new state
     */
    public static function shotclockTransition(string $state, string $command): string
    {
        return match ($command) {
            'start60', 'start40' => self::SHOTCLOCK_RUNNING,
            'stop' => self::SHOTCLOCK_IDLE,
            'gameClockStopped' => $state === self::SHOTCLOCK_RUNNING ? self::SHOTCLOCK_SUSPENDED : $state,
            'gameClockStarted' => $state === self::SHOTCLOCK_SUSPENDED ? self::SHOTCLOCK_RUNNING : $state,
            default => throw new \InvalidArgumentException("Unknown shotclock command: $command"),
        };
    }
}
