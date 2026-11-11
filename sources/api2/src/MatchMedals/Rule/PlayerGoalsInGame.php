<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\AwardScope;
use App\MatchMedals\Model\DataLevel;

/** N goals or more by one player in the game (hat-trick, four-goal haul). Score level: counts the goals, no time needed. */
abstract class PlayerGoalsInGame implements MedalRule
{
    /** Goals needed, from the thresholds of the game. */
    abstract protected function required(MatchTimeline $game): int;

    final public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    final public function award(MatchTimeline $game): array
    {
        $goals = [];
        foreach ($game->goalEvents() as $event) {
            if ($event->player !== null && !$event->player->isCoach) {
                $goals[$event->player->matric] ??= ['side' => $event->side, 'count' => 0];
                ++$goals[$event->player->matric]['count'];
            }
        }
        $awards = [];
        foreach ($goals as $matric => ['side' => $side, 'count' => $count]) {
            if ($count >= $this->required($game)) {
                $awards[] = new Award($this->code(), AwardScope::Player, $side, $matric);
            }
        }

        return $awards;
    }
}
