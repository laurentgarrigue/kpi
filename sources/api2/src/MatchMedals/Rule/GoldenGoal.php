<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\AwardScope;
use App\MatchMedals\Model\DataLevel;
use App\MatchMedals\Model\GameEvent;

/** But en or · Golden Goal: wins in overtime; the player scores the golden goal. Score level: no time needed. */
final class GoldenGoal implements MedalRule
{
    public function code(): string
    {
        return 'golden_goal';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    public function award(MatchTimeline $game): array
    {
        $winner = $game->winner();
        if ($winner === null || !$game->isOvertime()) {
            return [];
        }
        $awards = [Award::team($this->code(), $winner)];
        $overtimeGoals = array_values(array_filter(
            $game->goalEvents(),
            static fn (GameEvent $e): bool => $e->side === $winner && str_starts_with((string) $e->period, 'P'),
        ));
        $golden = $overtimeGoals[count($overtimeGoals) - 1] ?? null;
        if ($golden?->player !== null && !$golden->player->isCoach) {
            $awards[] = new Award($this->code(), AwardScope::Player, $winner, $golden->player->matric, $golden->id);
        }

        return $awards;
    }
}
