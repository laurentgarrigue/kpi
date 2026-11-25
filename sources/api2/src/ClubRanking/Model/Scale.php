<?php

namespace App\ClubRanking\Model;

/**
 * The scale of one season (§ 3): its rules, its categories and the perimeter of the national competitions (§ 5.2:
 * codes starting with one of the prefixes, minus the excluded codes — not `Code_niveau = 'NAT'`, which also holds
 * UNSS, OPEND…).
 */
final class Scale
{
    public const DEFAULT_NATIONAL_PREFIXES = ['N', 'CF'];

    /** @var array<string, Category> */
    private array $categories = [];

    /**
     * @param list<Rule>     $rules
     * @param list<Category> $categories
     * @param list<string>   $nationalPrefixes
     * @param list<string>   $excludedCompetitions
     */
    public function __construct(
        public readonly string $season,
        public readonly array $rules,
        array $categories,
        public readonly array $nationalPrefixes = self::DEFAULT_NATIONAL_PREFIXES,
        public readonly array $excludedCompetitions = [],
    ) {
        foreach ($categories as $category) {
            $this->categories[$category->code] = $category;
        }
    }

    /** @return list<Category> */
    public function categories(): array
    {
        return array_values($this->categories);
    }

    public function category(string $code): ?Category
    {
        return $this->categories[$code] ?? null;
    }

    public function rule(string $code): ?Rule
    {
        foreach ($this->rules as $rule) {
            if ($rule->code() === $code) {
                return $rule;
            }
        }

        return null;
    }

    public function isNational(string $competition): bool
    {
        if (in_array($competition, $this->excludedCompetitions, true)) {
            return false;
        }
        foreach ($this->nationalPrefixes as $prefix) {
            if ($prefix !== '' && str_starts_with($competition, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /** First category covering a competition, null when none (the competition then stands for itself). */
    public function categoryOf(string $competition): ?Category
    {
        foreach ($this->categories as $category) {
            if ($category->covers($competition)) {
                return $category;
            }
        }

        return null;
    }

    /** Categories with a final competition: their gamedays are finals (§ 5.7). */
    public function isFinalCompetition(string $competition): bool
    {
        foreach ($this->categories as $category) {
            if ($category->finalCode === $competition) {
                return true;
            }
        }

        return false;
    }
}
