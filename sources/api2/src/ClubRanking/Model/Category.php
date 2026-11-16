<?php

namespace App\ClubRanking\Model;

/**
 * Category of the annex (U15, N1D, Play Off H…) for one season (§ 3.2): the KPI competitions where its teams are
 * engaged (a pattern ending with `*` covers every code with that prefix: `N15*` = N15, N15A, N15_2…), the
 * competition of its final ranking and the competition of its regular season.
 */
final class Category
{
    private const WILDCARD = '*';

    /** @param list<string> $entryCodes */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly array $entryCodes,
        public readonly ?string $finalCode = null,
        public readonly ?string $regularCode = null,
        public readonly ?int $id = null,
    ) {
    }

    public function covers(string $competition): bool
    {
        if ($competition === $this->finalCode || $competition === $this->regularCode) {
            return true;
        }
        foreach ($this->entryCodes as $pattern) {
            if (str_ends_with($pattern, self::WILDCARD)
                ? str_starts_with($competition, substr($pattern, 0, -1))
                : $competition === $pattern) {
                return true;
            }
        }

        return false;
    }

    public function hasCompetitions(): bool
    {
        return $this->entryCodes !== [] || $this->finalCode !== null || $this->regularCode !== null;
    }

    /** Competition of a ranking slot: `final` or `regular` season. */
    public function rankingCompetition(string $ranking): ?string
    {
        return $ranking === RankingSlot::REGULAR ? $this->regularCode : $this->finalCode;
    }
}
