<?php

namespace App\AgeCategory;

/** Changes of the Categ cache, planned (not written yet) or applied. */
final class AgeCategoryChanges implements \Countable
{
    /** Shown for an absent category in countsBySeason(). */
    public const NONE = '∅';

    /** @param list<AgeCategoryChange> $changes */
    public function __construct(private readonly array $changes)
    {
    }

    /** @return list<AgeCategoryChange> */
    public function all(): array
    {
        return $this->changes;
    }

    public function count(): int
    {
        return count($this->changes);
    }

    /**
     * Number of changes by season and by « before → after » couple (a NULL or empty category reads NONE).
     *
     * @return array<string, array<string, int>>
     */
    public function countsBySeason(): array
    {
        $counts = [];
        foreach ($this->changes as $change) {
            $couple = self::label($change->before) . ' → ' . self::label($change->after);
            $counts[$change->season][$couple] = ($counts[$change->season][$couple] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    private static function label(?string $category): string
    {
        return $category === null || $category === '' ? self::NONE : $category;
    }
}
