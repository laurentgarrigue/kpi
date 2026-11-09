<?php

namespace App\MatchMedals\Model;

/** One fact of the game timeline (kp_match_detail): a goal or a card. */
final readonly class GameEvent
{
    public const GOAL = 'B';

    /**
     * @param string   $side      'A' or 'B'
     * @param string   $code      B (goal), V, J, R, D (cards)
     * @param int|null $remaining remaining time on the period clock, in seconds (the clock counts down)
     */
    public function __construct(
        public string $id,
        public string $side,
        public string $code,
        public ?string $period,
        public ?int $remaining,
        public ?Player $player = null,
        public ?string $motif = null,
    ) {
    }

    public function isGoal(): bool
    {
        return $this->code === self::GOAL;
    }
}
