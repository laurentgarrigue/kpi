<?php

namespace App\MatchMedals\Model;

/** A goal placed on the continuous time axis of the game, with what the analysis found about it. */
final class TimedGoal
{
    public bool $isPowerplay = false;
    public bool $isShorthanded = false;
    public bool $isDecisive = false;

    /** @param array{A: int, B: int} $scoreAfter */
    public function __construct(
        public readonly GameEvent $event,
        public readonly int $seconds,
        public readonly int $periodLength,
        public readonly array $scoreAfter,
    ) {
    }

    public function side(): string
    {
        return $this->event->side;
    }

    /** Seconds since the start of the goal's period. */
    public function secondsInPeriod(): int
    {
        return $this->periodLength - (int) $this->event->remaining;
    }
}
