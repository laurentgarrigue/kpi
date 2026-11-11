<?php

namespace App\MatchMedals\Stats;

/** Médailles des arbitres · Referee Medals, sorted by games officiated. */
final class MedalsRefereesStat extends AbstractRefereeStat
{
    public function code(): string
    {
        return 'medals_referees';
    }

    public function columns(): array
    {
        return [['key' => 'games', 'type' => 'integer'], ['key' => 'total', 'type' => 'integer']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $medals = [];
        foreach ($q->data->medals() as $m) {
            if ($m['scope'] === 'referee' && $q->isVisible($m['code'])) {
                $medals[$m['matric']][$m['code']] = ($medals[$m['matric']][$m['code']] ?? 0) + 1;
            }
        }
        $rows = [];
        foreach (self::byReferee($q) as $matric => $entry) {
            $rows[] = ['referee' => $entry['referee'], 'games' => count($entry['games']), 'medals' => $medals[$matric] ?? [], 'total' => array_sum($medals[$matric] ?? [])];
        }

        return ['rows' => self::ranked($rows, 'games', static fn (array $r): string => self::name($r['referee']), $q->limit)];
    }
}
