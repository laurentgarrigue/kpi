<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchMedalCalculator;
use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Triplé consécutif · Natural Hat-trick: N goals in a row of the game by the same player; one medal per run. */
final class NaturalHatTrick implements MedalRule
{
    public function code(): string
    {
        return 'natural_hat_trick';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        $awards = [];
        $scorer = null;
        $run = 0;
        foreach ($game->goals() as $goal) {
            $player = $goal->event->player;
            $current = $player !== null && !$player->isCoach ? $player->matric : null;
            $run = $current !== null && $current === $scorer ? $run + 1 : 1;
            $scorer = $current;
            if ($current !== null && $run === $game->thresholds->hatTrickGoals) {
                $awards[] = Award::scorer($this->code(), $goal);
            }
        }

        return MatchMedalCalculator::present($awards);
    }
}
