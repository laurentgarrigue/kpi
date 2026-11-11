<?php

namespace App\MatchMedals\Stats;

use App\MatchMedals\MedalThresholds;

/**
 * Festival · Goal Fest (§ 3.3): total goals among the top N % of the competition, from N games on. It depends on
 * the whole competition, so it is decided when read, not stored with the game.
 */
final class GoalFest
{
    public const CODE = 'goal_fest';

    /**
     * @param list<int> $totals total goals of every eligible game of the competition
     *
     * @return int|null smallest total that makes a goal fest; null when the competition has too few games
     */
    public static function threshold(array $totals, MedalThresholds $thresholds): ?int
    {
        if (count($totals) < $thresholds->goalFestMinGames) {
            return null;
        }
        rsort($totals);
        $position = max(1, (int) ceil(count($totals) * $thresholds->goalFestTopPercent / 100));

        return max(1, $totals[$position - 1]);
    }
}
