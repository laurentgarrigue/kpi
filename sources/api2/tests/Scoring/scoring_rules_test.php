<?php

/**
 * Standalone tests for ScoringRules (plan lot 1.2 — pure logic, test-first).
 *
 * api2 has no test framework yet (require-dev = phpstan only), so this file is a
 * zero-dependency runner: `php sources/api2/tests/Scoring/scoring_rules_test.php`.
 * Exit code 0 = all green. Migrate to PHPUnit as-is when a test pack lands.
 */

require __DIR__ . '/../../src/Scoring/ScoringRules.php';

use App\Scoring\ScoringRules as R;

$failures = 0;
$asserts = 0;

function check(string $label, mixed $expected, mixed $actual): void
{
    global $failures, $asserts;
    $asserts++;
    if ($expected !== $actual) {
        $failures++;
        fwrite(STDERR, sprintf(
            "FAIL %s\n  expected: %s\n  actual:   %s\n",
            $label,
            var_export($expected, true),
            var_export($actual, true)
        ));
    }
}

// ---------------------------------------------------------------- periods
check('P1 is overtime', true, R::isOvertime('P1'));
check('P12 is overtime (unbounded)', true, R::isOvertime('P12'));
check('M1 is not overtime', false, R::isOvertime('M1'));
check('TB is not overtime', false, R::isOvertime('TB'));
check('overtime index of P7', 7, R::overtimeIndex('P7'));
check('overtime index of M2', null, R::overtimeIndex('M2'));

// type C (classement): M1 → M2 → end, draw allowed
check('C: after M1', 'M2', R::nextPeriod('C', 'M1', true));
check('C: after M2 even level', null, R::nextPeriod('C', 'M2', true));

// type E (élimination): unbounded overtimes while level
check('E: after M1', 'M2', R::nextPeriod('E', 'M1', false));
check('E: after M2 with winner', null, R::nextPeriod('E', 'M2', false));
check('E: after M2 level', 'P1', R::nextPeriod('E', 'M2', true));
check('E: after P1 level', 'P2', R::nextPeriod('E', 'P1', true));
check('E: after P9 level (no cap)', 'P10', R::nextPeriod('E', 'P9', true));
check('E: after P1 with winner (golden goal happened)', null, R::nextPeriod('E', 'P1', false));
check('E: after TB', null, R::nextPeriod('E', 'TB', true));

// golden goal
check('golden goal in E/P3', true, R::goalEndsMatch('E', 'P3'));
check('no golden goal in E/M2', false, R::goalEndsMatch('E', 'M2'));
check('no golden goal in C', false, R::goalEndsMatch('C', 'P1'));

// durations (defaults: M=600, P=300 — 5 min ICF & FFCK, TB=180)
check('duration M1 default', 600, R::periodDuration('M1', []));
check('duration P4 default 5 min', 300, R::periodDuration('P4', []));
check('duration P4 from config', 240, R::periodDuration('P4', ['P' => 240]));
check('duration TB default', 180, R::periodDuration('TB', []));

// inter-period breaks (3'/3'/1', §4.10)
check('break before M2', 180, R::breakDurationBefore('M2'));
check('break before P1', 180, R::breakDurationBefore('P1'));
check('break before P2', 60, R::breakDurationBefore('P2'));
check('break before P5 (between overtimes)', 60, R::breakDurationBefore('P5'));
check('no break before M1', null, R::breakDurationBefore('M1'));
check('no break before TB', null, R::breakDurationBefore('TB'));
check('break override', 120, R::breakDurationBefore('M2', ['halftime' => 120]));

// ---------------------------------------------------------------- cards (2027 rules)
check('first card may be V', true, R::validateCardProgression([], 'V'));
check('first card may be J', true, R::validateCardProgression([], 'J'));
check('first card may be R', true, R::validateCardProgression([], 'R'));
check('second identical card refused', 'card_not_higher', R::validateCardProgression(['J'], 'J'));
check('lower card refused', 'card_not_higher', R::validateCardProgression(['J'], 'V'));
check('higher card allowed', true, R::validateCardProgression(['V'], 'J'));
check('V then R allowed (skip J)', true, R::validateCardProgression(['V'], 'R'));
check('after R nothing more', 'player_already_out', R::validateCardProgression(['J', 'R'], 'D'));
check('after D nothing more', 'player_already_out', R::validateCardProgression(['D'], 'V'));
check('black card anytime', true, R::validateCardProgression(['V', 'J'], 'D'));
check('unknown card refused', 'unknown_card', R::validateCardProgression([], 'X'));

// who returns after the penalty (§0.9)
check('V player returns', true, R::playerReturnsAfterPenalty('V'));
check('J player returns', true, R::playerReturnsAfterPenalty('J'));
check('R player replaced (at penalty end only)', false, R::playerReturnsAfterPenalty('R'));
check('D player never replaced', false, R::playerReturnsAfterPenalty('D'));

// which cards start a penalty clock (§7.4, correction 2026-07-29)
check('V starts a penalty clock', true, R::cardCreatesPenaltyClock('V'));
check('J starts a penalty clock', true, R::cardCreatesPenaltyClock('J'));
check('R starts a penalty clock', true, R::cardCreatesPenaltyClock('R'));
check('D starts NO penalty clock (definitive exclusion)', false, R::cardCreatesPenaltyClock('D'));

// early lift on conceded goal: V/J only — R runs its full 2 minutes
check('V liftable on conceded goal', true, R::penaltyLiftableOnGoal('V'));
check('J liftable on conceded goal', true, R::penaltyLiftableOnGoal('J'));
check('R NOT liftable on conceded goal', false, R::penaltyLiftableOnGoal('R'));

// ---------------------------------------------------------------- penalties
check('free slot when none busy', 1, R::freePenaltySlot([]));
check('free slot when 1 busy', 2, R::freePenaltySlot([1]));
check('free slot when 2 busy', 1, R::freePenaltySlot([2]));
check('no third concurrent penalty', null, R::freePenaltySlot([1, 2]));

check('lift oldest liftable penalty', 2, R::penaltySlotToLift([
    ['slot' => 1, 'startedAt' => '2026-07-27 15:04:10.000', 'cardCode' => 'V'],
    ['slot' => 2, 'startedAt' => '2026-07-27 15:02:00.000', 'cardCode' => 'J'],
]));
check('lift single V penalty', 1, R::penaltySlotToLift([
    ['slot' => 1, 'startedAt' => '2026-07-27 15:04:10.000', 'cardCode' => 'V'],
]));
check('R penalty never lifted early (full 2 minutes)', null, R::penaltySlotToLift([
    ['slot' => 1, 'startedAt' => '2026-07-27 15:02:00.000', 'cardCode' => 'R'],
]));
check('oldest is R → lift the younger J instead', 2, R::penaltySlotToLift([
    ['slot' => 1, 'startedAt' => '2026-07-27 15:02:00.000', 'cardCode' => 'R'],
    ['slot' => 2, 'startedAt' => '2026-07-27 15:04:10.000', 'cardCode' => 'J'],
]));
check('nothing to lift', null, R::penaltySlotToLift([]));

// ---------------------------------------------------------------- shotclock (3 commands)
check('idle + start60 runs', R::SHOTCLOCK_RUNNING, R::shotclockTransition(R::SHOTCLOCK_IDLE, 'start60'));
check('idle + start40 runs', R::SHOTCLOCK_RUNNING, R::shotclockTransition(R::SHOTCLOCK_IDLE, 'start40'));
check('running + start60 = reset, still running', R::SHOTCLOCK_RUNNING, R::shotclockTransition(R::SHOTCLOCK_RUNNING, 'start60'));
check('running + stop = idle (not a pause)', R::SHOTCLOCK_IDLE, R::shotclockTransition(R::SHOTCLOCK_RUNNING, 'stop'));
check('game clock stop suspends a running shotclock', R::SHOTCLOCK_SUSPENDED, R::shotclockTransition(R::SHOTCLOCK_RUNNING, 'gameClockStopped'));
check('game clock stop leaves idle alone', R::SHOTCLOCK_IDLE, R::shotclockTransition(R::SHOTCLOCK_IDLE, 'gameClockStopped'));
check('game clock start resumes a suspended shotclock', R::SHOTCLOCK_RUNNING, R::shotclockTransition(R::SHOTCLOCK_SUSPENDED, 'gameClockStarted'));
check('game clock start leaves idle alone', R::SHOTCLOCK_IDLE, R::shotclockTransition(R::SHOTCLOCK_IDLE, 'gameClockStarted'));
check('suspended + stop = idle', R::SHOTCLOCK_IDLE, R::shotclockTransition(R::SHOTCLOCK_SUSPENDED, 'stop'));
check('suspended + start40 = reset + running', R::SHOTCLOCK_RUNNING, R::shotclockTransition(R::SHOTCLOCK_SUSPENDED, 'start40'));

$thrown = false;
try {
    R::shotclockTransition(R::SHOTCLOCK_IDLE, 'pause');
} catch (InvalidArgumentException) {
    $thrown = true;
}
check('there is no pause command (decision 2026-07-27)', true, $thrown);

// ---------------------------------------------------------------- report
if ($failures > 0) {
    fwrite(STDERR, "\n{$failures}/{$asserts} assertions FAILED\n");
    exit(1);
}
echo "OK — {$asserts} assertions passed\n";
