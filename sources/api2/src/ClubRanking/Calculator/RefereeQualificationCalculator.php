<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Data\Referee;
use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use App\ClubRanking\Model\Scale;

/**
 * Referees, table officials and young officials (7.3.3, 7.3.5 to 7.3.7): once per licensee of the season whose
 * qualification (kp_arbitre `arbitre` / `niveau`, `*` = any level) is one of the rule (I-18). Nothing else is
 * required, except `min_national_games` (7.3.3 « officiant en compétition nationale »). One qualification per
 * licensee in KPI: an international referee is not also a national A (I-17).
 */
final class RefereeQualificationCalculator implements RuleCalculator
{
    private const ANY_LEVEL = '*';

    public function type(): RuleType
    {
        return RuleType::RefereeQualification;
    }

    public function validate(Rule $rule, Scale $scale): array
    {
        return self::qualifications($rule) === [] ? [sprintf('%s : aucune qualification d\'arbitre.', $rule->code())] : [];
    }

    public function compute(Rule $rule, CalculationContext $context): CalculationResult
    {
        $qualifications = self::qualifications($rule);
        $minGames = $rule->int('min_national_games', 0);
        $lines = [];
        foreach ($context->data->referees as $referee) {
            if ($referee->nationalGames < $minGames || !self::matches($referee, $qualifications)) {
                continue;
            }
            $club = $context->clubOfLicensee($referee->matric);
            if ($club !== null) {
                $lines[] = new Line($rule->code(), $club, $rule->points, sprintf('%s — %s', $context->data->licensee($referee->matric)?->name() ?? $referee->matric, trim($referee->qualification . ' ' . $referee->level)), Line::SOURCE_LICENSEE, (string) $referee->matric, $referee->matric);
            }
        }

        return new CalculationResult($lines);
    }

    /** @param list<array{qualification: string, level: string}> $qualifications */
    private static function matches(Referee $referee, array $qualifications): bool
    {
        foreach ($qualifications as $qualification) {
            if (strcasecmp($referee->qualification, $qualification['qualification']) === 0
                && ($qualification['level'] === self::ANY_LEVEL || strcasecmp($referee->level, $qualification['level']) === 0)) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{qualification: string, level: string}> */
    private static function qualifications(Rule $rule): array
    {
        $qualifications = [];
        foreach ($rule->params['qualifications'] ?? [] as $item) {
            if (is_array($item) && is_string($item['qualification'] ?? null) && $item['qualification'] !== '') {
                $qualifications[] = ['qualification' => $item['qualification'], 'level' => is_string($item['level'] ?? null) && $item['level'] !== '' ? $item['level'] : self::ANY_LEVEL];
            }
        }

        return $qualifications;
    }
}
