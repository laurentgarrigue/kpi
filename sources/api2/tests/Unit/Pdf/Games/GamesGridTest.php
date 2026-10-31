<?php

namespace App\Tests\Unit\Pdf\Games;

use App\Pdf\Games\GamesGrid;
use PHPUnit\Framework\TestCase;

/** Pitch grid (PDF_GOTENBERG.md § 7.3.1). */
final class GamesGridTest extends TestCase
{
    public function testPdfData05OneColumnPerPitchUsedInNaturalOrder(): void
    {
        $games = [self::game('10'), self::game('6'), self::game('5'), self::game('8'), self::game('7'), self::game('6')];

        self::assertSame(['5', '6', '7', '8', '10'], GamesGrid::pitches($games));
        self::assertSame(['1', '-'], GamesGrid::pitches([self::game(''), self::game('1')]), 'games without pitch last');
    }

    public function testPdfData06BeyondSixPitchesTheColumnsAreSplitIntoBalancedGroups(): void
    {
        self::assertSame([6], array_map('count', GamesGrid::groups(range(1, 6))));
        self::assertSame([4, 4], array_map('count', GamesGrid::groups(range(1, 8))));
        self::assertSame([5, 4], array_map('count', GamesGrid::groups(range(1, 9))));
        self::assertSame([5, 4, 4], array_map('count', GamesGrid::groups(range(1, 13))));
        self::assertSame([], GamesGrid::groups([]));
    }

    public function testPdfData06EachGroupRepeatsTheTimeSlots(): void
    {
        $games = [];
        foreach (range(1, 8) as $pitch) {
            $games[] = self::game((string) $pitch, '09:00', $pitch);
        }
        $sections = GamesGrid::sections($games, 'teams', ['games.time' => 'Heure', 'games.pitch' => 'Terrain'], static fn (string $date, string $place): string => $date . ' ' . $place);

        self::assertCount(2, $sections);
        foreach ($sections as $section) {
            self::assertSame('time', $section['columns'][0]['key']);
            self::assertCount(5, $section['columns'], 'time + 4 pitches');
            self::assertSame('09:00', $section['rows'][0]['time']);
        }
        self::assertSame('#5 Alpha - Bravo', $sections[1]['rows'][0]['pitch_5']);
    }

    public function testPdfData07TeamsOrPhaseOfEachGame(): void
    {
        self::assertSame('#3 Alpha - Bravo', GamesGrid::cell(self::game('1', '10:00', 3), 'teams'));
        self::assertSame('#3 Poule A M3', GamesGrid::cell(self::game('1', '10:00', 3), 'phases'));
        self::assertSame('#3 [V1-V2]', GamesGrid::cell(['LibelleA' => null, 'LibelleB' => null, 'Libelle' => '[V1-V2]'] + self::game('1', '10:00', 3), 'teams'), 'teams not drawn yet');
    }

    /** @return array<string, mixed> */
    private static function game(string $pitch, string $time = '10:00', int $number = 1): array
    {
        return [
            'Terrain' => $pitch, 'Heure_match' => $time, 'Numero_ordre' => $number, 'Date_match' => '2026-05-01', 'Lieu' => 'Lacville',
            'Phase' => 'Poule A', 'Libelle' => 'M' . $number, 'LibelleA' => 'Alpha', 'LibelleB' => 'Bravo',
        ];
    }
}
