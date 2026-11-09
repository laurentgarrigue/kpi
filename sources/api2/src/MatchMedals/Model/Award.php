<?php

namespace App\MatchMedals\Model;

/** One medal or label given by a game, to a team, a player, a referee or to the game itself. */
final readonly class Award
{
    public function __construct(
        public string $code,
        public AwardScope $scope,
        public ?string $side = null,
        public ?int $matric = null,
        public ?string $eventId = null,
        public ?int $gameSeconds = null,
    ) {
    }

    public static function team(string $code, string $side, ?TimedGoal $goal = null): self
    {
        return new self($code, AwardScope::Team, $side, null, $goal?->event->id, $goal?->seconds);
    }

    /** Null when the goal has no player medal to give (no scorer, or a coach). */
    public static function scorer(string $code, TimedGoal $goal): ?self
    {
        $player = $goal->event->player;
        if ($player === null || $player->isCoach) {
            return null;
        }

        return new self($code, AwardScope::Player, $goal->event->side, $player->matric, $goal->event->id, $goal->seconds);
    }

    public static function referee(string $code, Referee $referee): self
    {
        return new self($code, AwardScope::Referee, null, $referee->matric);
    }

    public static function label(string $code): self
    {
        return new self($code, AwardScope::Game);
    }
}
