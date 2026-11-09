<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\AwardScope;
use App\MatchMedals\Model\DataLevel;

/** Triplé · Hat-trick: N goals or more in the game. Score level: counts the goals, no time needed. */
final class HatTrick implements MedalRule
{
    public function code(): string
    {
        return 'hat_trick';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    public function award(MatchTimeline $game): array
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
            if ($count >= $game->thresholds->hatTrickGoals) {
                $awards[] = new Award($this->code(), AwardScope::Player, $side, $matric);
            }
        }

        return $awards;
    }
}
