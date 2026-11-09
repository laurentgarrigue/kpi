<?php

namespace App\MatchMedals\Stats;

/** Médailles des joueurs · Player Medals. */
final class MedalsPlayersStat extends AbstractMedalStat
{
    public function code(): string
    {
        return 'medals_players';
    }

    public function columns(): array
    {
        return [['key' => 'total', 'type' => 'integer']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $players = [];
        foreach ($q->data->medals() as $m) {
            if ($m['scope'] !== 'player' || $m['team_id'] === null || !$q->isVisible($m['code'])) {
                continue;
            }
            $key = $m['team_id'].'-'.$m['matric'];
            $players[$key] ??= [...$q->data->player($m['team_id'], (int) $m['matric']), 'team' => $q->data->team($m['team_id']), 'medals' => [], 'total' => 0];
            $players[$key]['medals'][$m['code']] = ($players[$key]['medals'][$m['code']] ?? 0) + 1;
            ++$players[$key]['total'];
        }

        return ['rows' => self::ranked(array_values($players), 'total', self::name(...), $q->limit)];
    }
}
