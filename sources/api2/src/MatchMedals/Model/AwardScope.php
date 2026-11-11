<?php

namespace App\MatchMedals\Model;

enum AwardScope: string
{
    case Team = 'team';
    case Player = 'player';
    case Referee = 'referee';
    /** A label of the whole game (Thriller…). */
    case Game = 'game';
}
