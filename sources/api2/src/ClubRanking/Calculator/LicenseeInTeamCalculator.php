<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use App\ClubRanking\Model\Scale;

/**
 * « Un ou plusieurs licenciés dans une équipe … » (7.1.2): one unit per team and per club having at least one player
 * in it (I-3), selections included (I-6), the club of the team included unless `include_team_club` is false (I-1).
 * A player counts if he played at least one validated game, or with `presence: roster` if he is on the roster; a
 * coach never counts (I-4).
 */
final class LicenseeInTeamCalculator implements RuleCalculator
{
    public const PRESENCE_ROSTER = 'roster';

    public function type(): RuleType
    {
        return RuleType::LicenseeInTeam;
    }

    public function validate(Rule $rule, Scale $scale): array
    {
        return CategoryParam::validate($rule, $scale);
    }

    public function compute(Rule $rule, CalculationContext $context): CalculationResult
    {
        $rosterIsEnough = $rule->string('presence', 'played') === self::PRESENCE_ROSTER;
        $includeTeamClub = $rule->bool('include_team_club', true);
        $lines = [];
        foreach (CategoryParam::resolve($rule, $context->scale) as $category) {
            foreach ($context->teamsOf($category) as $teamNumber => $entries) {
                $team = $entries[0];
                foreach ($context->clubsOfPlayers($entries, $rosterIsEnough) as $club) {
                    if (!$includeTeamClub && $club === $team->clubCode) {
                        continue;
                    }
                    $lines[] = new Line($rule->code(), $club, $rule->points, sprintf('Licencié(s) dans l\'équipe %s — %s', $team->label, $category->label), Line::SOURCE_TEAM, $teamNumber . ':' . $category->code);
                }
            }
        }

        return new CalculationResult($lines);
    }
}
