<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Model\Alert;
use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use App\ClubRanking\Model\Scale;

/**
 * Rules KPI cannot compute (7.1.4 Équipe de France, 7.2.5 formations, 7.3.4 CNA, § 7): lines typed by hand, a
 * licensee (his club at the reference date) or directly a club. `unit: club` pays a club once, `unit: licensee` once
 * per licensee. `exclude_rules` leaves out a licensee already paid by those rules (7.3.4 « hors RC, délégué CNA,
 * juge arbitre », CNC-20).
 */
final class ManualListCalculator implements RuleCalculator
{
    public const UNIT_CLUB = 'club';
    public const UNIT_LICENSEE = 'licensee';

    public function type(): RuleType
    {
        return RuleType::ManualList;
    }

    public function validate(Rule $rule, Scale $scale): array
    {
        return in_array($rule->params['unit'] ?? null, [self::UNIT_CLUB, self::UNIT_LICENSEE], true)
            ? []
            : [sprintf('%s : unité attendue « club » ou « licensee ».', $rule->code())];
    }

    public function compute(Rule $rule, CalculationContext $context): CalculationResult
    {
        $perClub = $rule->string('unit', self::UNIT_CLUB) === self::UNIT_CLUB;
        $excluded = array_flip(self::excludedMatrics($rule, $context->previousLines));
        $lines = [];
        $alerts = [];
        $paid = [];
        foreach ($context->data->manualEntries as $entry) {
            if ($entry->ruleCode !== $rule->code()) {
                continue;
            }
            $name = $entry->matric === null ? null : ($context->data->licensee($entry->matric)?->name() ?? (string) $entry->matric);
            if ($entry->matric !== null && isset($excluded[$entry->matric])) {
                $alerts[] = new Alert(Alert::EXCLUDED_ENTRY, sprintf('%s : déjà compté en %s, exclu de %s.', $name, implode(' / ', $rule->stringList('exclude_rules')), $rule->code()), $rule->code(), context: ['entry' => $entry->id]);
                continue;
            }
            $club = $entry->clubCode !== null && $entry->clubCode !== ''
                ? ($context->data->isEligibleClub($entry->clubCode) ? $entry->clubCode : null)
                : ($entry->matric === null ? null : $context->clubOfLicensee($entry->matric));
            if ($club === null) {
                continue;
            }
            $unitKey = $perClub ? $club : ($entry->matric !== null ? 'm' . $entry->matric : 'e' . $entry->id);
            if (isset($paid[$unitKey])) {
                continue;
            }
            $paid[$unitKey] = true;
            $reason = trim(($name ?? '') . ($entry->detail !== '' ? ' — ' . $entry->detail : ''));
            $lines[] = new Line($rule->code(), $club, $rule->points, $reason !== '' ? $reason : 'Saisie manuelle', Line::SOURCE_ENTRY, (string) $entry->id, $entry->matric);
        }

        return new CalculationResult($lines, $alerts);
    }

    /**
     * Licensees paid by the rules this one excludes.
     *
     * @param list<Line> $lines
     *
     * @return list<int>
     */
    public static function excludedMatrics(Rule $rule, array $lines): array
    {
        $excludedRules = $rule->stringList('exclude_rules');
        $matrics = [];
        foreach ($lines as $line) {
            if ($line->matric !== null && in_array($line->ruleCode, $excludedRules, true)) {
                $matrics[$line->matric] = true;
            }
        }

        return array_keys($matrics);
    }
}
