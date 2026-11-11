<?php

namespace App\MatchMedals\Stats;

/** Premier but gagnant · First Goal Wins: share of the decided games won by the team that opens the score. */
final class OpenerWinsStat extends AbstractMedalStat
{
    public function code(): string
    {
        return 'opener_wins';
    }

    public function columns(): array
    {
        return [['key' => 'opened', 'type' => 'integer'], ['key' => 'won', 'type' => 'integer'], ['key' => 'percent', 'type' => 'percent']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $won = $decided = 0;
        $teams = [];
        foreach ($q->data->games(true) as $game) {
            $side = $game['first_goal_side'];
            if ($side === null || $game['winner'] === null) {
                continue;
            }
            ++$decided;
            $isWon = $side === $game['winner'];
            $won += (int) $isWon;
            $team = self::teamOf($q, $game, $side);
            if ($team !== null) {
                $teams[$team['id']] ??= ['team' => $team, 'opened' => 0, 'won' => 0];
                ++$teams[$team['id']]['opened'];
                $teams[$team['id']]['won'] += (int) $isWon;
            }
        }
        $rows = array_map(static fn (array $t): array => [...$t, 'percent' => self::percent($t['won'], $t['opened'])], array_values($teams));

        return [
            'rows' => self::ranked($rows, 'opened', static fn (array $r): string => (string) $r['team']['label'], $q->limit),
            'summary' => ['won' => $won, 'decided' => $decided, 'percent' => self::percent($won, $decided)],
        ];
    }
}
