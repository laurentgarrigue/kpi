<?php

namespace App\MatchMedals\Stats;

/** Résistance % · Penalty Kill %: share of the exclusions gone through without conceding, per team. */
final class PenaltyKillRateStat extends AbstractMedalStat
{
    public function code(): string
    {
        return 'penalty_kill_rate';
    }

    public function columns(): array
    {
        return [['key' => 'exclusions', 'type' => 'integer'], ['key' => 'killed', 'type' => 'integer'], ['key' => 'percent', 'type' => 'percent']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $rows = [];
        foreach (self::byTeam($q, true) as $entry) {
            $exclusions = $killed = 0;
            foreach ($entry['games'] as ['game' => $game, 'side' => $side]) {
                $exclusions += $game['exclusions'][$side];
                $killed += $game['penalty_killed'][$side];
            }
            $rows[] = ['team' => $entry['team'], 'exclusions' => $exclusions, 'killed' => $killed, 'percent' => self::percent($killed, $exclusions)];
        }

        return ['rows' => self::ranked($rows, 'percent', static fn (array $r): string => (string) $r['team']['label'], $q->limit)];
    }
}
