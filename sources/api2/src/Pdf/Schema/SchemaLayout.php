<?php

namespace App\Pdf\Schema;

use App\Pdf\PdfPlace;

/**
 * Layout of the schema of a competition (`schema`, PDF_GOTENBERG.md § 7.3), as the app4 Schema page draws it: a cup
 * in stage columns (pool rankings, elimination brackets with the winner first), a league as its gamedays in date
 * order. Pure functions on CompetitionSchemaService::schema(), unit-tested.
 */
final class SchemaLayout
{
    private const ELIMINATION = 'E';

    /**
     * Stage columns of a cup, each with its phases in the order of the schema.
     *
     * @param list<array<string, mixed>> $phases
     *
     * @return list<list<array<string, mixed>>>
     */
    public static function stages(array $phases): array
    {
        $stages = [];
        foreach ($phases as $phase) {
            $stages[(int) $phase['etape']][] = self::cupPhase($phase);
        }
        ksort($stages);

        return array_values($stages);
    }

    /**
     * Gamedays of a league, by start date then name.
     *
     * @param list<array<string, mixed>> $phases
     *
     * @return list<array<string, mixed>>
     */
    public static function gamedays(array $phases): array
    {
        usort($phases, static fn (array $a, array $b): int => [(string) $a['dateDebut'], self::gamedayName($a)] <=> [(string) $b['dateDebut'], self::gamedayName($b)]);

        return array_map(static fn (array $phase): array => [
            'title' => self::gamedayName($phase),
            'phase' => $phase['nom'] !== '' && $phase['phase'] !== '' ? $phase['phase'] : null,
            'place' => PdfPlace::label($phase['lieu'], $phase['departement']),
            'dates' => self::dateRange($phase['dateDebut'], $phase['dateFin']),
            'games' => array_map(self::game(...), $phase['matches']),
        ], $phases);
    }

    /** « 10:00-17:30 », or the single time. */
    public static function timeRange(?string $start, ?string $end): ?string
    {
        if ($start === null || $start === '') {
            return null;
        }
        $start = substr($start, 0, 5);
        $end = $end === null ? '' : substr($end, 0, 5);

        return $end === '' || $end === $start ? $start : $start . '-' . $end;
    }

    /** « 01/05/2026 - 02/05/2026 », or the single date. */
    public static function dateRange(?string $start, ?string $end): string
    {
        $format = static function (?string $date): string {
            $parsed = $date === null ? false : \DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));

            return $parsed === false ? '' : $parsed->format('d/m/Y');
        };

        return $end === null || $end === $start || $format($end) === '' ? $format($start) : $format($start) . ' - ' . $format($end);
    }

    /**
     * @param array<string, mixed> $phase
     *
     * @return array<string, mixed>
     */
    private static function cupPhase(array $phase): array
    {
        $elimination = $phase['type'] === self::ELIMINATION;
        $games = $phase['matches'];
        usort($games, static fn (array $a, array $b): int => ($a['numeroOrdre'] ?? PHP_INT_MAX) <=> ($b['numeroOrdre'] ?? PHP_INT_MAX));

        return [
            'title' => (string) $phase['phase'],
            'games_count' => (int) $phase['nbMatchs'],
            'times' => self::timeRange($phase['startTime'], $phase['endTime']),
            'elimination' => $elimination,
            'ranking' => $elimination ? [] : ($phase['ranking'] ?? []),
            // Without ranking: the teams of the pool, else those of its games (placeholders included), else blank lines.
            'teams' => $elimination || !empty($phase['ranking']) ? [] : self::poolTeams($phase),
            'blank_lines' => $elimination || !empty($phase['ranking']) || self::poolTeams($phase) !== [] ? 0 : (int) $phase['nbequipes'],
            'brackets' => $elimination ? array_map(self::bracket(...), $games) : [],
        ];
    }

    /**
     * @param array<string, mixed> $phase
     *
     * @return list<string>
     */
    private static function poolTeams(array $phase): array
    {
        if (!empty($phase['poolTeams'])) {
            return array_column($phase['poolTeams'], 'libelle');
        }
        $teams = [];
        foreach ($phase['matches'] as $game) {
            foreach ([$game['equipeA'], $game['equipeB']] as $team) {
                if ($team !== '' && !in_array($team, $teams, true)) {
                    $teams[] = $team;
                }
            }
        }
        natcasesort($teams);

        return array_values($teams);
    }

    /**
     * A bracket game: the winner first, with the scores of a validated game.
     *
     * @param array<string, mixed> $game
     *
     * @return array{number: ?int, teams: list<array{name: string, score: ?string, winner: bool}>}
     */
    private static function bracket(array $game): array
    {
        $result = self::game($game);
        $teams = [
            ['name' => $result['home'], 'score' => $result['home_score'], 'winner' => $result['winner'] === 'A'],
            ['name' => $result['away'], 'score' => $result['away_score'], 'winner' => $result['winner'] === 'B'],
        ];

        return ['number' => $game['numeroOrdre'], 'teams' => $result['winner'] === 'B' ? array_reverse($teams) : $teams];
    }

    /**
     * @param array<string, mixed> $game
     *
     * @return array{home: string, away: string, home_score: ?string, away_score: ?string, winner: ?string}
     */
    private static function game(array $game): array
    {
        $scored = $game['scoreA'] !== null && $game['scoreB'] !== null;
        $a = (int) $game['scoreA'];
        $b = (int) $game['scoreB'];

        return [
            'home' => (string) $game['equipeA'],
            'away' => (string) $game['equipeB'],
            'home_score' => $scored ? (string) $game['scoreA'] : null,
            'away_score' => $scored ? (string) $game['scoreB'] : null,
            'winner' => !$scored || $a === $b ? null : ($a > $b ? 'A' : 'B'),
        ];
    }

    /** @param array<string, mixed> $phase */
    private static function gamedayName(array $phase): string
    {
        return (string) ($phase['nom'] ?: $phase['phase']);
    }
}
