<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use App\ClubRanking\Model\Scale;

/**
 * « Responsable de compétition » (7.3.2): once per licensee responsible for at least one competition of the national
 * perimeter (kp_rc, preliminary phases included), whatever the number of competitions (I-16). The RC without
 * competition (« - CNA - ») are not competition managers.
 */
final class CompetitionManagerCalculator implements RuleCalculator
{
    public function type(): RuleType
    {
        return RuleType::CompetitionManager;
    }

    public function validate(Rule $rule, Scale $scale): array
    {
        return [];
    }

    public function compute(Rule $rule, CalculationContext $context): CalculationResult
    {
        $competitions = [];
        foreach ($context->data->competitionManagers as $manager) {
            if ($manager->competition !== null && $context->scale->isNational($manager->competition)) {
                $competitions[$manager->matric][] = $manager->competition;
            }
        }
        $lines = [];
        foreach ($competitions as $matric => $codes) {
            $club = $context->clubOfLicensee($matric);
            if ($club !== null) {
                $codes = array_values(array_unique($codes));
                sort($codes);
                $lines[] = new Line($rule->code(), $club, $rule->points, sprintf('%s — RC %s', $context->data->licensee($matric)?->name() ?? $matric, implode(', ', $codes)), Line::SOURCE_LICENSEE, (string) $matric, $matric);
            }
        }

        return new CalculationResult($lines);
    }
}
