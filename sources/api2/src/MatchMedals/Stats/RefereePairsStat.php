<?php

namespace App\MatchMedals\Stats;

/** Binômes · Referee Pairs: designated pairs who officiated together, games and cards per game. */
final class RefereePairsStat extends AbstractRefereeStat
{
    public function code(): string
    {
        return 'referee_pairs';
    }

    public function columns(): array
    {
        return [['key' => 'games', 'type' => 'integer'], ['key' => 'per_game', 'type' => 'decimal']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $byGame = [];
        foreach ($q->data->referees() as $r) {
            $byGame[$r['match_id']][] = $r['matric'];
        }
        $games = $q->data->games();
        $pairs = [];
        foreach ($byGame as $gameId => $matrics) {
            if (count($matrics) !== 2 || !isset($games[$gameId])) {
                continue;
            }
            sort($matrics);
            $key = implode('-', $matrics);
            $pairs[$key] ??= ['referees' => array_map(fn (int $m): array => $q->data->referee($m), $matrics), 'games' => 0, 'cards' => 0];
            ++$pairs[$key]['games'];
            $pairs[$key]['cards'] += count(self::cardsOf($q, $gameId));
        }
        $rows = array_map(fn (array $p): array => [
            'referees' => $p['referees'],
            'games' => $p['games'],
            'per_game' => $q->hasEnoughGames($p['games']) ? self::average($p['cards'], $p['games']) : null,
        ], array_values($pairs));

        return ['rows' => self::ranked($rows, 'games', static fn (array $r): string => self::name($r['referees'][0]).self::name($r['referees'][1]), $q->limit)];
    }
}
