<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Model\Alert;
use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use App\ClubRanking\Model\Scale;

/**
 * « Équipe du club en … (par équipe de club engagée) » (7.1.1, 7.1.3): one unit per club team and per category,
 * whatever its number of competitions in the category (§ 5.4). Each club of a declared entente earns it (I-5); a
 * regional or departmental selection earns nothing as a team (I-6). By default a team must have played at least
 * one validated game (I-23).
 */
final class TeamOfClubCalculator implements RuleCalculator
{
    public function type(): RuleType
    {
        return RuleType::TeamOfClub;
    }

    public function validate(Rule $rule, Scale $scale): array
    {
        return CategoryParam::validate($rule, $scale);
    }

    public function compute(Rule $rule, CalculationContext $context): CalculationResult
    {
        $requirePlayed = $rule->bool('require_played', true);
        $withEntente = $rule->bool('ententes', true);
        $lines = [];
        $alerts = [];
        foreach (CategoryParam::resolve($rule, $context->scale) as $category) {
            foreach ($context->teamsOf($category) as $teamNumber => $entries) {
                if ($requirePlayed && CalculationContext::validatedGames($entries) === 0) {
                    continue;
                }
                $team = $entries[0];
                if (!$context->data->isEligibleClub($team->clubCode)) {
                    $alerts[] = new Alert(Alert::SELECTION_TEAM, sprintf('%s (%s) : équipe d\'une structure qui n\'est pas un club, aucun point au titre de l\'équipe.', $team->label, $category->code), $rule->code(), context: ['team' => $teamNumber, 'club' => $team->clubCode]);
                }
                foreach ($context->clubsOfTeam($entries, $withEntente) as $club) {
                    $lines[] = new Line($rule->code(), $club, $rule->points, sprintf('Équipe %s — %s', $team->label, $category->label), Line::SOURCE_TEAM, $teamNumber . ':' . $category->code);
                }
            }
        }

        return new CalculationResult($lines, $alerts);
    }
}
