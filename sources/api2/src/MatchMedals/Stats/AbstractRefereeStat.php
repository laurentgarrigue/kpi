<?php

namespace App\MatchMedals\Stats;

/**
 * Referee statistics (§ 3.5): every fact of a game counts for both its designated referees; rows are sorted by
 * games officiated then by name, never by cards; an indicator is null under the minimum number of games.
 */
abstract class AbstractRefereeStat extends AbstractMedalStat
{
    public function needsReliableTimeline(): bool
    {
        return false;
    }

    /**
     * @param callable(list<array{game: array, role: string}>): array<string, mixed> $indicators
     *
     * @return list<array<string, mixed>>
     */
    protected function refereeRows(MedalStatQuery $q, callable $indicators, bool $reliableOnly = false): array
    {
        $rows = [];
        foreach (self::byReferee($q, $reliableOnly) as $entry) {
            $games = count($entry['games']);
            $values = $q->hasEnoughGames($games) ? $indicators($entry['games']) : [];
            $rows[] = ['referee' => $entry['referee'], 'games' => $games, ...$values];
        }

        return self::ranked($rows, 'games', static fn (array $r): string => self::name($r['referee']), $q->limit);
    }
}
