<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Model\Category;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\Scale;

/** The `categories` parameter shared by the team calculators: its checks (CNC-03) and its resolution. */
final class CategoryParam
{
    public const NAME = 'categories';

    /** @return list<string> */
    public static function validate(Rule $rule, Scale $scale): array
    {
        $codes = $rule->stringList(self::NAME);
        if ($codes === []) {
            return [sprintf('%s : aucune catégorie.', $rule->code())];
        }

        return self::validateCodes($rule, $scale, $codes);
    }

    /**
     * @param list<string> $codes
     *
     * @return list<string>
     */
    public static function validateCodes(Rule $rule, Scale $scale, array $codes): array
    {
        $errors = [];
        foreach ($codes as $code) {
            $category = $scale->category($code);
            if ($category === null) {
                $errors[] = sprintf('%s : catégorie « %s » inconnue.', $rule->code(), $code);
            } elseif (!$category->hasCompetitions()) {
                $errors[] = sprintf('%s : la catégorie « %s » n\'a aucun code de compétition.', $rule->code(), $code);
            }
        }

        return $errors;
    }

    /** @return list<Category> */
    public static function resolve(Rule $rule, Scale $scale): array
    {
        return array_values(array_filter(array_map(
            static fn (string $code): ?Category => $scale->category($code),
            $rule->stringList(self::NAME),
        )));
    }
}
