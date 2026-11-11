<?php

namespace App\MatchMedals\Stats;

use App\PublicResults\CompetitionRules;

/** Shared helpers of the timeline statistics: sides, team rows, shared ranks, rates. */
abstract class AbstractMedalStat implements MedalStat
{
    protected const SIDES = ['A', 'B'];

    public function needsReliableTimeline(): bool
    {
        return true;
    }

    public function columns(): array
    {
        return [];
    }

    /** @return array{id: int, number: int|null, label: string, logo: string|null}|null */
    protected static function teamOf(MedalStatQuery $q, array $game, string $side): ?array
    {
        return $q->data->team($side === 'A' ? $game['team_a'] : $game['team_b']);
    }

    protected static function other(string $side): string
    {
        return $side === 'A' ? 'B' : 'A';
    }

    /** Percentage rounded to the unit, null without a denominator. */
    protected static function percent(int $part, int $whole): ?int
    {
        return $whole === 0 ? null : (int) round(100 * $part / $whole);
    }

    protected static function average(int|float $total, int $count, int $decimals = 1): ?float
    {
        return $count === 0 ? null : round($total / $count, $decimals);
    }

    /**
     * Sorts by a value (descending), then by a label, adds the shared rank (1, 2, 2, 4), cuts at the limit.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return list<array<string, mixed>>
     */
    protected static function ranked(array $rows, string $valueKey, callable $label, int $limit): array
    {
        usort($rows, static fn (array $a, array $b): int => [$b[$valueKey] ?? -1, $label($a)] <=> [$a[$valueKey] ?? -1, $label($b)]);
        $rows = array_slice($rows, 0, $limit);
        $ranks = CompetitionRules::sharedRanks(array_map(static fn (array $r): mixed => $r[$valueKey], $rows));

        return array_map(static fn (array $r, int $rank): array => ['rank' => $rank, ...$r], $rows, $ranks);
    }

    /** Per team of the perimeter, the games it played (eligible, optionally reliable). @return array<int, array{team: array, games: list<array{game: array, side: string}>}> */
    protected static function byTeam(MedalStatQuery $q, bool $reliableOnly): array
    {
        $teams = [];
        foreach ($q->data->games($reliableOnly) as $game) {
            foreach (self::SIDES as $side) {
                $team = self::teamOf($q, $game, $side);
                if ($team !== null) {
                    $teams[$team['id']] ??= ['team' => $team, 'games' => []];
                    $teams[$team['id']]['games'][] = ['game' => $game, 'side' => $side];
                }
            }
        }

        return $teams;
    }

    /** Per designated referee, the games officiated (each game once). @return array<int, array{referee: array, games: list<array{game: array, role: string}>}> */
    protected static function byReferee(MedalStatQuery $q, bool $reliableOnly = false): array
    {
        $games = $q->data->games($reliableOnly);
        $referees = [];
        foreach ($q->data->referees() as $r) {
            if (isset($games[$r['match_id']])) {
                $referees[$r['matric']] ??= ['referee' => $q->data->referee($r['matric']), 'games' => []];
                $referees[$r['matric']]['games'][] = ['game' => $games[$r['match_id']], 'role' => $r['role']];
            }
        }

        return $referees;
    }

    /** Games with at least one designated referee: the basis of the referee averages (§ 5.6). @return array<int, array> */
    protected static function refereedGames(MedalStatQuery $q, bool $reliableOnly = false): array
    {
        $ids = array_flip(array_column($q->data->referees(), 'match_id'));

        return array_filter($q->data->games($reliableOnly), static fn (array $g): bool => isset($ids[$g['id']]));
    }

    /** @return list<array<string, mixed>> cards of a game */
    protected static function cardsOf(MedalStatQuery $q, int $gameId): array
    {
        return $q->data->cardsByGame()[$gameId] ?? [];
    }

    protected static function name(array $person): string
    {
        return mb_strtolower(($person['last_name'] ?? '').' '.($person['first_name'] ?? ''));
    }
}
