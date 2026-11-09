<?php

namespace App\MatchMedals\Stats;

/** Moment des cartons · Card Timing: cards of the games officiated by slice of playing time (reliable timelines). */
final class RefereeCardTimingStat extends AbstractRefereeStat
{
    public function needsReliableTimeline(): bool
    {
        return true;
    }

    public function code(): string
    {
        return 'referee_card_timing';
    }

    public function columns(): array
    {
        return [['key' => 'games', 'type' => 'integer'], ['key' => 'slices', 'type' => 'histogram']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $slices = function (array $games) use ($q): array {
            $slice = $q->thresholds->goalTimingSliceSeconds;
            $regulation = 2 * $q->thresholds->regularPeriodSeconds;
            $counts = array_fill(0, (int) ceil($regulation / $slice) + 1, 0);
            foreach ($games as $game) {
                foreach (self::cardsOf($q, $game['id']) as $card) {
                    if ($card['seconds'] !== null) {
                        ++$counts[min(count($counts) - 1, intdiv($card['seconds'], $slice))];
                    }
                }
            }

            return $counts;
        };

        return [
            'rows' => $this->refereeRows($q, static fn (array $officiated): array => ['slices' => $slices(array_column($officiated, 'game'))], true),
            'summary' => ['slices' => $slices(self::refereedGames($q, true)), 'slice_seconds' => $q->thresholds->goalTimingSliceSeconds],
        ];
    }
}
