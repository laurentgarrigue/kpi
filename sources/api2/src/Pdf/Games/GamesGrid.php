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
            $days[$game['Date_match'] . '|' . $game['Lieu']][] = $game;
        }

        $sections = [];
        foreach ($days as $dayGames) {
            $first = $dayGames[0];
            foreach (self::groups(self::pitches($dayGames)) as $index => $pitches) {
                $sections[] = [
                    'title' => $dayLabel($first['Date_match'], $first['Lieu']),
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
     * @return list<array<string, string>>
     */
    private static function rows(array $games, array $pitches, string $view): array
    {
        $slots = [];
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
        }
        ksort($slots, SORT_NATURAL);

        return array_values($slots);
    }

    /** Text of a game: its number, then its teams or its phase. */
    public static function cell(array $game, string $view): string
    {
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
