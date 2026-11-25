<?php

namespace App\ClubRanking\Model;

/** Calculation types of the scale (PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 3.3): a rule of the annex is one of them, configured. */
enum RuleType: string
{
    case TeamOfClub = 'TEAM_OF_CLUB';
    case LicenseeInTeam = 'LICENSEE_IN_TEAM';
    case Ranking = 'RANKING';
    case EventOrganiser = 'EVENT_ORGANISER';
    case GamedayOfficial = 'JOURNEE_OFFICIAL';
    case CompetitionManager = 'COMPETITION_MANAGER';
    case RefereeQualification = 'REFEREE_QUALIFICATION';
    case ManualList = 'MANUAL_LIST';

    /** Manual lists depend on the computed lines (exclusions of 7.3.4): they run last. */
    public function runsLast(): bool
    {
        return $this === self::ManualList;
    }
}
