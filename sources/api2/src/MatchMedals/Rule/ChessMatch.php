<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;

/** Partie d’échecs · Chess Match (game label): N goals or fewer in the game. */
final class ChessMatch implements MedalRule
{
    public function code(): string
    {
        return 'chess_match';
    }

    public function dataLevel(): DataLevel
    {
        return DataLevel::Score;
    }

    public function award(MatchTimeline $game): array
    {
        return $game->score('A') + $game->score('B') <= $game->thresholds->chessMatchMaxGoals ? [Award::label($this->code())] : [];
    }
}
