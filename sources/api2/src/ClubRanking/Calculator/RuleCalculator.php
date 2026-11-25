<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use App\ClubRanking\Model\Scale;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/** One calculation type of § 3.3: turns a configured rule into lines of detail. */
#[AutoconfigureTag(self::TAG)]
interface RuleCalculator
{
    public const TAG = 'app.club_ranking_calculator';

    public function type(): RuleType;

    /**
     * Configuration errors of the rule (§ 3.3 « barème valide »): the calculation is refused while there are some.
     *
     * @return list<string>
     */
    public function validate(Rule $rule, Scale $scale): array;

    public function compute(Rule $rule, CalculationContext $context): CalculationResult;
}
