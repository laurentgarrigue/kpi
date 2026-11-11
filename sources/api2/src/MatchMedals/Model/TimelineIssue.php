<?php

namespace App\MatchMedals\Model;

/** Why the timeline of an eligible game is not reliable: the first rule of § 5.1 it breaks. */
enum TimelineIssue: string
{
    /** Rule 4: the goals do not add up to the score. */
    case ScoreMismatch = 'score_mismatch';
    /** Rules 5 and 6: a fact without a time (or at 00:00) or with an unknown period. */
    case Untimed = 'untimed';
    /** Rule 7: a fact at the start of its period, the time a chrono keeps when nothing was typed. */
    case PeriodStart = 'period_start';
    /** Rule 8: two goals of a period at the same second. */
    case SameInstant = 'same_instant';
}
