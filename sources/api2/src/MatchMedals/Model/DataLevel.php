<?php

namespace App\MatchMedals\Model;

/** Data a rule needs (§ 3.1): the score only (S), or a reliable timeline (T). */
enum DataLevel: string
{
    case Score = 'S';
    case Timeline = 'T';
}
