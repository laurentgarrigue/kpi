<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Retour au score · Fightback: trailed by N goals or more, and draws (game without overtime). */
final class Fightback implements MedalRule
{
    public function code(): string
    {
        return 'fightback';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Timeline;
    }

    public function award(MatchTimeline $game): array
    {
        if ($game->winner() !== null || $game->isOvertime()) {
            return [];
        }
        $sides = array_filter(MatchTimeline::SIDES, static fn (string $s): bool => $game->maxDeficit($s) >= $game->thresholds->comebackDeficit);

        return array_map(fn (string $s): Award => Award::team($this->code(), $s), array_values($sides));
    }
}
