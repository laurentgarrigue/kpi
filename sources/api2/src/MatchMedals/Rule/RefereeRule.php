<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\Referee;

/** Referee medals (§ 3.5): given to every designated referee of the game when the condition holds. */
abstract class RefereeRule implements MedalRule
{
    abstract protected function holds(MatchTimeline $game): bool;

    final public function award(MatchTimeline $game): array
    {
        return $this->holds($game) ? array_map(fn (Referee $r): Award => Award::referee($this->code(), $r), $game->referees()) : [];
    }
}
