<?php

namespace App\ClubRanking;

use App\ClubRanking\Calculator\CalculationContext;
use App\ClubRanking\Calculator\CalculationResult;
use App\ClubRanking\Calculator\RuleCalculator;
use App\ClubRanking\Data\SeasonData;
use App\ClubRanking\Model\Alert;
use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use App\ClubRanking\Model\Scale;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Computes every rule of a season's scale on its data (§ 8 step 2): refuses an invalid scale, runs the manual lists
 * last, never pays twice the same award, and warns when the clubs are read after the end of the season (§ 5.1).
 */
final class ClubRankingEngine
{
    /** @var array<string, RuleCalculator> */
    private array $calculators = [];

    /** @param iterable<RuleCalculator> $calculators */
    public function __construct(#[AutowireIterator(RuleCalculator::TAG)] iterable $calculators)
    {
        foreach ($calculators as $calculator) {
            $this->calculators[$calculator->type()->value] = $calculator;
        }
    }

    /** @return list<string> configuration errors of the scale, empty when it can be computed */
    public function validate(Scale $scale): array
    {
        $errors = [];
        $codes = [];
        foreach ($scale->rules as $rule) {
            if (isset($codes[$rule->code()])) {
                $errors[] = sprintf('%s : rubrique en double.', $rule->code());
            }
            $codes[$rule->code()] = true;
            $errors = [...$errors, ...$this->calculator($rule->type)->validate($rule, $scale)];
        }

        return $errors;
    }

    /** @throws InvalidScale */
    public function compute(Scale $scale, SeasonData $data, \DateTimeImmutable $now): CalculationResult
    {
        $errors = $this->validate($scale);
        if ($errors !== []) {
            throw new InvalidScale($errors);
        }
        $alerts = [];
        if ($now->format('Y') > $scale->season) {
            $alerts[] = new Alert(Alert::AFTER_SEASON, sprintf('Calcul après la fin de la saison %s : clubs des licenciés et qualifications d\'arbitre lus au %s.', $scale->season, $now->format('d/m/Y')));
        }
        $rules = $scale->rules;
        usort($rules, static fn (Rule $a, Rule $b): int => [$a->type->runsLast(), $a->article, $a->rank] <=> [$b->type->runsLast(), $b->article, $b->rank]);
        $lines = [];
        $context = new CalculationContext($scale, $data);
        foreach ($rules as $rule) {
            $result = $this->calculator($rule->type)->compute($rule, $context->withLines(array_values($lines)));
            foreach ($result->lines as $line) {
                $lines[$line->key()] ??= $line;
            }
            foreach ($result->alerts as $alert) {
                $alerts[$alert->key()] ??= $alert;
            }
        }
        $lines = array_values($lines);
        usort($lines, static fn (Line $a, Line $b): int => [$a->ruleCode, $a->clubCode, $a->sourceId] <=> [$b->ruleCode, $b->clubCode, $b->sourceId]);

        return new CalculationResult($lines, array_values($alerts));
    }

    private function calculator(RuleType $type): RuleCalculator
    {
        return $this->calculators[$type->value] ?? throw new \LogicException(sprintf('No calculator for %s.', $type->value));
    }
}
