<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Data\Gameday;
use App\ClubRanking\Model\Alert;
use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use App\ClubRanking\Model\Scale;
use App\ClubRanking\NameNormalizer;

/**
 * « Délégué CNA ou Juge arbitre sur une ou plusieurs journées de compétition nationale » (7.3.1): once per licensee
 * (I-16). The gameday stores free-text names (N-5): a name is matched through the manual matches first, then to
 * the only licensee with that « NOM Prénom » or « Prénom NOM »; otherwise it is an alert to match by hand.
 */
final class GamedayOfficialCalculator implements RuleCalculator
{
    public const FIELD_DELEGATE = 'delegate';
    public const FIELD_CHIEF_REFEREE = 'chief_referee';
    private const FIELDS = [self::FIELD_DELEGATE, self::FIELD_CHIEF_REFEREE];

    public function type(): RuleType
    {
        return RuleType::GamedayOfficial;
    }

    public function validate(Rule $rule, Scale $scale): array
    {
        $fields = $rule->stringList('fields');

        return $fields !== [] && array_diff($fields, self::FIELDS) === []
            ? []
            : [sprintf('%s : champs attendus parmi %s.', $rule->code(), implode(', ', self::FIELDS))];
    }

    public function compute(Rule $rule, CalculationContext $context): CalculationResult
    {
        $names = $this->namesOfNationalGamedays($rule, $context);
        $competitions = $this->competitionsOfGamedays($context);
        $lines = [];
        $alerts = [];
        $paid = [];
        foreach ($names as $name => $gamedays) {
            $candidates = $this->candidates($name, $context);
            if (count($candidates) !== 1) {
                $alerts[] = new Alert(Alert::OFFICIAL_UNRESOLVED, sprintf('« %s » (journées %s) : %s, à rapprocher d\'un licencié.', $name, implode(', ', $gamedays), $candidates === [] ? 'aucun licencié de ce nom' : count($candidates) . ' licenciés de ce nom'), $rule->code(), context: ['name' => $name, 'gamedays' => $gamedays, 'gameday_competitions' => array_intersect_key($competitions, array_flip($gamedays)), 'candidates' => $candidates]);
                continue;
            }
            $matric = $candidates[0];
            $club = $context->clubOfLicensee($matric);
            if ($club === null || isset($paid[$matric])) {
                continue;
            }
            $paid[$matric] = true;
            $licensee = $context->data->licensee($matric);
            $lines[] = new Line($rule->code(), $club, $rule->points, sprintf('%s — journées %s', $licensee?->name() ?? $name, implode(', ', $gamedays)), Line::SOURCE_LICENSEE, (string) $matric, $matric);
        }

        return new CalculationResult($lines, $alerts);
    }

    /** @return array<string, list<int>> raw name → its national gamedays */
    /** @return list<int> a licence number written after the name wins, then a stored matching, then the name itself */
    private function candidates(string $name, CalculationContext $context): array
    {
        $written = NameNormalizer::matric($name);
        if ($written !== null && $context->data->licensee($written) !== null) {
            return [$written];
        }
        $alias = $context->data->alias($name);

        return $alias !== null ? [$alias] : $context->data->matricsNamed(NameNormalizer::withoutMatric($name));
    }

    /** @return array<int, string> gameday id → competition code */
    private function competitionsOfGamedays(CalculationContext $context): array
    {
        $competitions = [];
        foreach ($context->data->gamedays as $gameday) {
            $competitions[$gameday->id] = $gameday->competition;
        }

        return $competitions;
    }

    private function namesOfNationalGamedays(Rule $rule, CalculationContext $context): array
    {
        $names = [];
        foreach ($context->data->gamedays as $gameday) {
            if (!$context->scale->isNational($gameday->competition)) {
                continue;
            }
            foreach ($rule->stringList('fields') as $field) {
                foreach (NameNormalizer::split(self::field($gameday, $field)) as $name) {
                    $names[$name][] = $gameday->id;
                }
            }
        }

        return array_map(static fn (array $ids): array => array_values(array_unique($ids)), $names);
    }

    private static function field(Gameday $gameday, string $field): string
    {
        return $field === self::FIELD_DELEGATE ? $gameday->delegate : $gameday->chiefReferee;
    }
}
