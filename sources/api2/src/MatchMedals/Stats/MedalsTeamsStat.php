<?php

namespace App\MatchMedals\Stats;

/** Médailles des équipes · Team Medals: medals of each team by code; a player-only medal counts for its team. */
final class MedalsTeamsStat extends AbstractMedalStat
{
    public function code(): string
    {
        return 'medals_teams';
    }

    public function columns(): array
    {
        return [['key' => 'total', 'type' => 'integer']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $codes = self::teamCodes($q);
        $teams = [];
        foreach ($q->data->medals() as $m) {
            if ($m['team_id'] === null || !in_array($m['code'], $codes['all'], true)) {
                continue;
            }
            // a medal existing for both the team and the player counts once, as the team medal
            if ($m['scope'] === 'player' && in_array($m['code'], $codes['team'], true)) {
                continue;
            }
            $teams[$m['team_id']] ??= ['team' => $q->data->team($m['team_id']), 'medals' => [], 'total' => 0];
            $teams[$m['team_id']]['medals'][$m['code']] = ($teams[$m['team_id']]['medals'][$m['code']] ?? 0) + 1;
            ++$teams[$m['team_id']]['total'];
        }

        return ['rows' => self::ranked(array_values($teams), 'total', static fn (array $r): string => (string) $r['team']['label'], $q->limit), 'summary' => ['codes' => $codes['all']]];
    }

    /** @return array{all: list<string>, team: list<string>} visible medal codes counted for teams */
    private static function teamCodes(MedalStatQuery $q): array
    {
        $all = $team = [];
        foreach ($q->data->medals() as $m) {
            if ($m['scope'] !== 'referee' && $q->isVisible($m['code'])) {
                $all[$m['code']] = true;
                if ($m['scope'] === 'team') {
                    $team[$m['code']] = true;
                }
            }
        }
        $order = array_flip($q->visibleCodes);
        $all = array_keys($all);
        usort($all, static fn (string $a, string $b): int => $order[$a] <=> $order[$b]);

        return ['all' => $all, 'team' => array_keys($team)];
    }
}
