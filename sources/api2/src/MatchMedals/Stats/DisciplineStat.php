<?php

namespace App\MatchMedals\Stats;

/** Discipline: cards per game and per team, by colour. */
final class DisciplineStat extends AbstractMedalStat
{
    private const CODES = ['V', 'J', 'R', 'D'];

    public function needsReliableTimeline(): bool
    {
        return false;
    }

    public function code(): string
    {
        return 'discipline';
    }

    public function columns(): array
    {
        return [['key' => 'cards', 'type' => 'integer'], ['key' => 'per_game', 'type' => 'decimal']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $rows = [];
        $total = 0;
        foreach (self::byTeam($q, false) as $entry) {
            $byCode = array_fill_keys(self::CODES, 0);
            foreach ($entry['games'] as ['game' => $game, 'side' => $side]) {
                foreach (self::cardsOf($q, $game['id']) as $card) {
                    if ($card['side'] === $side && isset($byCode[$card['code']])) {
                        ++$byCode[$card['code']];
                    }
                }
            }
            $cards = array_sum($byCode);
            $total += $cards;
            $rows[] = ['team' => $entry['team'], 'games' => count($entry['games']), 'by_code' => $byCode, 'cards' => $cards, 'per_game' => self::average($cards, count($entry['games']))];
        }

        return [
            'rows' => self::ranked($rows, 'cards', static fn (array $r): string => (string) $r['team']['label'], $q->limit),
            'summary' => ['per_game' => self::average($total, count($q->data->games()))],
        ];
    }
}
