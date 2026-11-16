<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Data\Gameday;

/** Gamedays of the same organiser, at the same place, on overlapping dates (§ 5.7): one organised event. */
final class OrganisedEvent
{
    public const INTERNATIONAL = 'international';
    public const FINAL_MULTI = 'final_multi';
    public const FINAL_SINGLE = 'final_single';
    public const DAY_MULTI = 'day_multi';
    public const DAY_SINGLE = 'day_single';
    public const NATURES = [self::INTERNATIONAL, self::FINAL_MULTI, self::FINAL_SINGLE, self::DAY_MULTI, self::DAY_SINGLE];

    /** @param non-empty-list<Gameday> $gamedays sorted by date */
    public function __construct(
        public readonly array $gamedays,
        public readonly string $nature,
    ) {
    }

    public function organiser(): string
    {
        return $this->gamedays[0]->organiserCode;
    }

    public function id(): string
    {
        return (string) min(array_map(static fn (Gameday $gameday): int => $gameday->id, $this->gamedays));
    }

    public function describe(): string
    {
        $first = $this->gamedays[0];
        $last = max(array_map(static fn (Gameday $gameday): string => (string) $gameday->lastDay(), $this->gamedays));
        $competitions = array_values(array_unique(array_map(static fn (Gameday $gameday): string => $gameday->competition, $this->gamedays)));
        sort($competitions);

        return sprintf('%s, %s%s : %s', trim($first->place) !== '' ? trim($first->place) : '?', $first->start, $last !== $first->start ? ' → ' . $last : '', implode(', ', $competitions));
    }
}
