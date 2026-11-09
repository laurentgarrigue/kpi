<?php

namespace App\MatchMedals;

use App\MatchMedals\Model\Exclusion;
use App\MatchMedals\Model\GameEvent;
use App\MatchMedals\Model\GameRecord;
use App\MatchMedals\Model\Referee;
use App\MatchMedals\Model\TimedGoal;

/**
 * Reads a game the way the medal rules need it (FEATURE_MATCH_MEDALS.md § 5): eligibility and reliability (§ 5.1),
 * continuous time axis (§ 5.2), exclusions and power play (§ 5.3), score progression (§ 5.5). Pure: no database.
 */
final class MatchTimeline
{
    public const SIDES = ['A', 'B'];
    public const SHOOTOUT = 'TB';
    private const LAST_REGULATION_PERIOD = 'M2';
    private const TIMED_PERIOD = '/^(M[12]|P[1-9]\d*)$/';
    private const SECONDS_PER_MINUTE = 60;
    private const MAX_COUNTED_EXCLUSIONS = 2;

    /** @var list<GameEvent> facts outside the shoot-out */
    private array $events;
    private bool $isEligible;
    private bool $isReliable;
    /** @var array{A: int, B: int} */
    private array $score = ['A' => 0, 'B' => 0];
    /** @var array<string, int> */
    private array $periodLength = [];
    /** @var array<string, int> */
    private array $periodOffset = [];
    /** @var list<TimedGoal> */
    private array $goals = [];
    /** @var list<array{event: GameEvent, seconds: int|null}> */
    private array $cards = [];
    /** @var array{A: list<Exclusion>, B: list<Exclusion>} */
    private array $exclusions = ['A' => [], 'B' => []];
    private int $leadChanges = 0;
    private int $equalizers = 0;
    /** @var array{A: int, B: int} */
    private array $maxDeficit = ['A' => 0, 'B' => 0];

    private function __construct(public readonly GameRecord $game, public readonly MedalThresholds $thresholds)
    {
        $this->events = array_values(array_filter($game->events, static fn (GameEvent $e): bool => $e->period !== self::SHOOTOUT));
        $this->isEligible = $game->isValidated && $game->isPublished && $game->hasTwoTeams
            && self::isScore($game->scoreA) && self::isScore($game->scoreB);
        if ($this->isEligible) {
            $this->score = ['A' => (int) $game->scoreA, 'B' => (int) $game->scoreB];
        }
        $this->isReliable = $this->isEligible && $this->hasCompleteTimeline();
        if ($this->isReliable) {
            $this->measurePeriods();
            $this->walkTimeline();
        } else {
            $this->cards = array_map(static fn (GameEvent $e): array => ['event' => $e, 'seconds' => null], $this->cardEvents());
        }
    }

    public static function analyse(GameRecord $game, MedalThresholds $thresholds): self
    {
        return new self($game, $thresholds);
    }

    public function isEligible(): bool
    {
        return $this->isEligible;
    }

    public function isReliable(): bool
    {
        return $this->isReliable;
    }

    public function score(string $side): int
    {
        return $this->score[$side];
    }

    public function winner(): ?string
    {
        return match (true) {
            $this->score['A'] > $this->score['B'] => 'A',
            $this->score['B'] > $this->score['A'] => 'B',
            default => null,
        };
    }

    public static function opponent(string $side): string
    {
        return $side === 'A' ? 'B' : 'A';
    }

    /** @return list<Referee> designated referees, once each even if typed in both roles (§ 5.6) */
    public function referees(): array
    {
        $unique = [];
        foreach ($this->game->referees as $referee) {
            $unique[$referee->matric] ??= $referee;
        }

        return array_values($unique);
    }

    public function isThriller(): bool
    {
        return $this->isReliable && $this->leadChanges >= $this->thresholds->thrillerLeadChanges;
    }

    public function isKnockout(): bool
    {
        return $this->game->type === GameRecord::TYPE_KNOCKOUT;
    }

    /** Number of overtime periods played (P1, P2…), from the facts or the final period of the game. */
    public function overtimes(): int
    {
        $periods = array_map(static fn (GameEvent $e): ?string => $e->period, $this->events);
        $periods[] = $this->game->finalPeriod;
        $numbers = array_map(
            static fn (?string $p): int => $p !== null && preg_match('/^P(\d+)$/', $p, $m) ? (int) $m[1] : 0,
            $periods,
        );

        return max([0, ...$numbers]);
    }

    public function isOvertime(): bool
    {
        return $this->overtimes() > 0;
    }

    /** @return list<GameEvent> goals outside the shoot-out, in recording order (score level: no time needed) */
    public function goalEvents(): array
    {
        return array_values(array_filter($this->events, static fn (GameEvent $e): bool => $e->isGoal()));
    }

    /** @return list<GameEvent> */
    public function cardEvents(): array
    {
        return array_values(array_filter($this->events, static fn (GameEvent $e): bool => !$e->isGoal()));
    }

    /** @return list<TimedGoal> goals in chronological order (reliable timeline only) */
    public function goals(): array
    {
        return $this->goals;
    }

    /** @return list<array{event: GameEvent, seconds: int|null}> cards, timed when the timeline is reliable */
    public function cards(): array
    {
        return $this->cards;
    }

    /** @return list<Exclusion> */
    public function exclusions(string $side): array
    {
        return $this->exclusions[$side];
    }

    public function leadChanges(): int
    {
        return $this->leadChanges;
    }

    public function equalizers(): int
    {
        return $this->equalizers;
    }

    public function maxDeficit(string $side): int
    {
        return $this->maxDeficit[$side];
    }

    public function firstGoal(): ?TimedGoal
    {
        return $this->goals[0] ?? null;
    }

    public function decisiveGoal(): ?TimedGoal
    {
        foreach ($this->goals as $goal) {
            if ($goal->isDecisive) {
                return $goal;
            }
        }

        return null;
    }

    /** Goal on the last regulation period, in its final window (late winner, lifeline). */
    public function isLate(TimedGoal $goal): bool
    {
        return $goal->event->period === self::LAST_REGULATION_PERIOD && (int) $goal->event->remaining < $this->thresholds->lateWindowSeconds;
    }

    /** Last goal before overtime, i.e. the one fixing the regulation score. */
    public function lastRegulationGoal(): ?TimedGoal
    {
        $regulation = array_values(array_filter($this->goals, static fn (TimedGoal $g): bool => str_starts_with((string) $g->event->period, 'M')));

        return $regulation === [] ? null : $regulation[count($regulation) - 1];
    }

    public function isRegulationPeriod(?string $period): bool
    {
        return $period !== null && str_starts_with($period, 'M');
    }

    /** Length retained for a period (§ 5.2). */
    public function periodLength(string $period): int
    {
        return $this->periodLength[$period];
    }

    /** @return array<string, int> offset of each period on the game axis, in chronological order */
    public function periodOffsets(): array
    {
        return $this->periodOffset;
    }

    private static function isScore(?string $score): bool
    {
        return $score !== null && preg_match('/^\d+$/', $score) === 1;
    }

    private function hasCompleteTimeline(): bool
    {
        foreach (self::SIDES as $side) {
            $goals = array_filter($this->goalEvents(), static fn (GameEvent $e): bool => $e->side === $side);
            if (count($goals) !== $this->score[$side]) {
                return false;
            }
        }
        foreach ($this->events as $event) {
            if ($event->remaining === null || $event->period === null || preg_match(self::TIMED_PERIOD, $event->period) !== 1) {
                return false;
            }
        }

        return true;
    }

    /** Default length, stretched to the whole minute above the largest remaining time recorded in the period. */
    private function measurePeriods(): void
    {
        $periods = ['M1', 'M2'];
        foreach ($this->events as $event) {
            $periods[] = (string) $event->period;
        }
        $periods = array_values(array_unique($periods));
        usort($periods, static fn (string $a, string $b): int => self::periodRank($a) <=> self::periodRank($b));
        $offset = 0;
        foreach ($periods as $period) {
            $base = $period[0] === 'P' ? $this->thresholds->extraPeriodSeconds : $this->thresholds->regularPeriodSeconds;
            $remaining = array_map(static fn (GameEvent $e): int => (int) $e->remaining, array_filter($this->events, static fn (GameEvent $e): bool => $e->period === $period));
            $recorded = (int) ceil(max([0, ...$remaining]) / self::SECONDS_PER_MINUTE) * self::SECONDS_PER_MINUTE;
            $this->periodLength[$period] = max($base, $recorded);
            $this->periodOffset[$period] = $offset;
            $offset += $this->periodLength[$period];
        }
    }

    private static function periodRank(string $period): int
    {
        return $period[0] === 'M' ? (int) $period[1] : 2 + (int) substr($period, 1);
    }

    private function secondsOf(GameEvent $event): int
    {
        $period = (string) $event->period;

        return $this->periodOffset[$period] + $this->periodLength[$period] - (int) $event->remaining;
    }

    /** Chronological walk: cards open exclusions, goals are classified then lift an exclusion (§ 5.3, § 5.5). */
    private function walkTimeline(): void
    {
        $timed = array_map(fn (GameEvent $e, int $i): array => ['event' => $e, 'seconds' => $this->secondsOf($e), 'order' => $i], $this->events, array_keys($this->events));
        // equal time: card before goal, then recording order
        usort($timed, static fn (array $a, array $b): int => [$a['seconds'], $a['event']->isGoal(), $a['order']] <=> [$b['seconds'], $b['event']->isGoal(), $b['order']]);
        $current = ['A' => 0, 'B' => 0];
        $leader = null;
        $loser = $this->winner() === null ? null : self::opponent($this->winner());
        foreach ($timed as ['event' => $event, 'seconds' => $seconds]) {
            if (!$event->isGoal()) {
                $this->openExclusion($event, $seconds);
                continue;
            }
            $side = $event->side;
            ++$current[$side];
            $goal = new TimedGoal($event, $seconds, $this->periodLength[(string) $event->period], $current);
            $this->classifyNumbers($goal);
            if ($side === $this->winner() && $loser !== null && $current[$side] === $this->score[$loser] + 1) {
                $goal->isDecisive = true;
            }
            $diff = $current['A'] - $current['B'];
            $nowLeader = $diff > 0 ? 'A' : ($diff < 0 ? 'B' : null);
            if ($diff === 0) {
                ++$this->equalizers;
            }
            if ($nowLeader !== null && $leader !== null && $nowLeader !== $leader) {
                ++$this->leadChanges;
            }
            $leader = $nowLeader ?? $leader;
            $this->maxDeficit['A'] = max($this->maxDeficit['A'], -$diff);
            $this->maxDeficit['B'] = max($this->maxDeficit['B'], $diff);
            $this->goals[] = $goal;
        }
    }

    private function openExclusion(GameEvent $card, int $seconds): void
    {
        $isPermanent = in_array($card->code, $this->thresholds->permanentCards, true);
        $this->exclusions[$card->side][] = new Exclusion($seconds, $isPermanent ? PHP_INT_MAX : $seconds + $this->thresholds->exclusionSeconds, $isPermanent);
        $this->cards[] = ['event' => $card, 'seconds' => $seconds];
    }

    /** Power play / shorthanded, then the conceding team's oldest temporary exclusion is lifted. */
    private function classifyNumbers(TimedGoal $goal): void
    {
        $side = $goal->side();
        $conceding = self::opponent($side);
        $mine = $this->activeCount($side, $goal->seconds);
        $theirs = $this->activeCount($conceding, $goal->seconds);
        $goal->isPowerplay = $theirs > $mine;
        $goal->isShorthanded = $mine > $theirs;
        $active = array_values(array_filter($this->exclusions[$conceding], static fn (Exclusion $x): bool => $x->isActiveAt($goal->seconds)));
        foreach ($active as $exclusion) {
            $exclusion->isKilled = false;
        }
        $temporary = array_values(array_filter($active, static fn (Exclusion $x): bool => !$x->isPermanent));
        usort($temporary, static fn (Exclusion $a, Exclusion $b): int => $a->start <=> $b->start);
        if ($temporary !== []) {
            $temporary[0]->end = $goal->seconds;
        }
    }

    private function activeCount(string $side, int $seconds): int
    {
        $active = array_filter($this->exclusions[$side], static fn (Exclusion $x): bool => $x->isActiveAt($seconds));

        return min(self::MAX_COUNTED_EXCLUSIONS, count($active));
    }
}
