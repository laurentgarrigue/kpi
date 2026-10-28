<?php

namespace App\Pdf\Games;

/**
 * Which games a document prints (PDF_GOTENBERG.md § 7.3): a scope (competitions of a season, an event, a gameday or
 * a list of games), filters of the Games page, and the visibility of the audience.
 */
final class GamesQuery
{
    /**
     * @param list<string>  $competitions
     * @param list<int>     $games
     * @param string[]|null $allowedCompetitions perimeter of the user, null when not limited
     * @param int[]|null    $allowedGamedays     gameday filter of the user, null when not limited
     */
    public function __construct(
        public readonly string $season,
        public readonly array $competitions = [],
        public readonly ?int $event = null,
        public readonly ?int $gameday = null,
        public readonly array $games = [],
        public readonly ?string $date = null,
        public readonly ?string $pitch = null,
        public readonly ?string $round = null,
        public readonly bool $unlockedOnly = false,
        public readonly GamesSort $sort = GamesSort::Date,
        public readonly GamesVisibility $visibility = GamesVisibility::All,
        public readonly ?array $allowedCompetitions = null,
        public readonly ?array $allowedGamedays = null,
    ) {
    }
}
