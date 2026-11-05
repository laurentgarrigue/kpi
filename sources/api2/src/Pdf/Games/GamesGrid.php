<?php

namespace App\Pdf\Games;

/**
 * Pitch grid (`games-grid`, PDF_GOTENBERG.md § 7.3.1): rows = time slots, columns = the pitches the selected games
 * actually use, in natural order. Beyond MAX_PITCHES columns, the pitches are split into balanced groups printed one
 * after the other, each repeating the time slots. One section per day and venue. Pure functions, unit-tested.
 */
final class GamesGrid
{
    public const VIEWS = ['teams', 'phases'];

    /** Narrowest readable cell on an A4 landscape page. */
    public const MAX_PITCHES = 6;

    /** Column of the games without a pitch. */
    public const NO_PITCH = '-';

    /** Virtual phases: their games only keep an empty slot visible in the grid (no teams, referees nor number). */
    public const BREAK_PHASES = ['Pause', 'Break'];

    /** Cell class of a slot holding only breaks, styled neutral in pdf.css. */
    public const BREAK_CELL_CLASS = 'pdf-grid__break';

    /**
     * Pitches used by the games, in natural order (2 before 10), the games without pitch last.
     *
     * @param list<array<string, mixed>> $games
     *
     * @return list<string>
     */
    public static function pitches(array $games): array
    {
        $pitches = array_values(array_unique(array_map(self::pitch(...), $games)));
        usort($pitches, static fn (string $a, string $b): int => ($a === self::NO_PITCH) <=> ($b === self::NO_PITCH) ?: strnatcmp($a, $b));

        return $pitches;
    }

    /**
     * Pitches split into balanced groups of at most MAX_PITCHES (8 → 4 + 4, 9 → 5 + 4).
     *
     * @param list<string> $pitches
     *
     * @return list<list<string>>
     */
    public static function groups(array $pitches, int $max = self::MAX_PITCHES): array
    {
        if ($pitches === []) {
            return [];
        }
        $count = (int) ceil(count($pitches) / $max);
        $groups = [];
        $offset = 0;
        for ($index = 0; $index < $count; ++$index) {
            $size = (int) ceil((count($pitches) - $offset) / ($count - $index));
            $groups[] = array_slice($pitches, $offset, $size);
            $offset += $size;
        }

        return $groups;
    }

    /**
     * Sections of the document: by day and venue, then by group of pitches.
     *
     * @param list<array<string, mixed>> $games
     * @param array<string, string>      $labels
     *
     * @return list<array<string, mixed>>
     */
    public static function sections(array $games, string $view, array $labels, callable $dayLabel): array
    {
        $days = [];
        foreach ($games as $game) {
            $days[(string) $game['Date_match']][] = $game;
        }

        $sections = [];
        foreach ($days as $dayGames) {
            $first = $dayGames[0];
            $places = array_values(array_unique(array_filter(array_map(static fn (array $game): string => trim((string) $game['Lieu']), $dayGames))));
            foreach (self::groups(self::pitches($dayGames)) as $index => $pitches) {
                $sections[] = [
                    'title' => $dayLabel($first['Date_match'], implode(' / ', $places)),
                    'new_page' => $index === 0 && $sections !== [],
                    'columns' => [
                        ['key' => 'time', 'label' => $labels['games.time'], 'class' => 'pdf-grid__time'],
                        ...array_map(static fn (string $pitch): array => [
                            'key' => 'pitch_' . $pitch,
                            'label' => $labels['games.pitch'] . ' ' . $pitch,
                            'class' => 'pdf-grid__cell',
                        ], $pitches),
                    ],
                    'rows' => self::rows($dayGames, $pitches, $view),
                ];
            }
        }

        return $sections;
    }

    /**
     * One row per time slot, one cell per pitch.
     *
     * @param list<array<string, mixed>> $games
     * @param list<string>               $pitches
     *
     * @return list<array<string, mixed>>
     */
    private static function rows(array $games, array $pitches, string $view): array
    {
        $slots = [];
        $onlyBreaks = [];
        foreach ($games as $game) {
            $pitch = self::pitch($game);
            if (!in_array($pitch, $pitches, true)) {
                continue;
            }
            $time = substr((string) $game['Heure_match'], 0, 5);
            $slots[$time] ??= ['time' => $time];
            $cell = self::cell($game, $view);
            $key = 'pitch_' . $pitch;
            $slots[$time][$key] = isset($slots[$time][$key]) ? $slots[$time][$key] . "\n" . $cell : $cell;
            $onlyBreaks[$time][$key] = ($onlyBreaks[$time][$key] ?? true) && self::isBreak($game);
        }
        ksort($slots, SORT_NATURAL);
        foreach ($slots as $time => &$slot) {
            $breakCells = array_filter($onlyBreaks[$time]);
            if ($breakCells !== []) {
                $slot['_cells'] = array_map(static fn (): string => self::BREAK_CELL_CLASS, $breakCells);
            }
        }
        unset($slot);

        return array_values($slots);
    }

    public static function isBreak(array $game): bool
    {
        return in_array($game['Phase'] ?? null, self::BREAK_PHASES, true);
    }

    /** Text of a game: its number, then its teams or its phase; a break is only its name. */
    public static function cell(array $game, string $view): string
    {
        if (self::isBreak($game)) {
            return (string) $game['Phase'];
        }
        $number = $game['Numero_ordre'] === null ? '' : '#' . $game['Numero_ordre'] . ' ';
        if ($view === 'phases') {
            return trim($number . trim((string) $game['Phase'] . ' ' . self::label($game)));
        }
        $teams = $game['LibelleA'] === null && $game['LibelleB'] === null
            ? self::label($game)
            : sprintf('%s - %s', $game['LibelleA'] ?? '?', $game['LibelleB'] ?? '?');

        return trim($number . $teams);
    }

    private static function label(array $game): string
    {
        return (string) $game['Libelle'];
    }

    private static function pitch(array $game): string
    {
        $pitch = trim((string) $game['Terrain']);

        return $pitch === '' ? self::NO_PITCH : $pitch;
    }
}
