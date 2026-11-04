<?php

namespace App\Tests\Unit\Pdf\Schema;

use App\Pdf\Schema\SchemaLayout;
use PHPUnit\Framework\TestCase;

/** Layout of the schema PDF, as the app4 Schema page draws it (PDF_GOTENBERG.md § 7.3). */
final class SchemaLayoutTest extends TestCase
{
    public function testACupIsDrawnInStageColumnsWithTheWinnerOfEachBracketFirst(): void
    {
        $stages = SchemaLayout::stages([
            self::phase(2, 'Finale', 'E', [self::game(2, 'Alpha', 'Bravo', '1', '3'), self::game(1, 'Charlie', 'Delta', null, null)]),
            self::phase(1, 'Poule A', 'C', [self::game(5, 'Alpha', 'Bravo', '2', '2')], ranking: [['clt' => 1, 'libelle' => 'Alpha', 'pts' => 4, 'j' => 2, 'diff' => 3]]),
            self::phase(1, 'Poule B', 'C', [self::game(6, '(1er Poule A)', 'Echo', null, null)]),
        ]);

        self::assertSame([['Poule A', 'Poule B'], ['Finale']], array_map(static fn (array $stage): array => array_column($stage, 'title'), $stages));
        self::assertSame('Alpha', $stages[0][0]['ranking'][0]['libelle'], 'pool with its published ranking');
        self::assertSame(['(1er Poule A)', 'Echo'], $stages[0][1]['teams'], 'pool without ranking: the teams of its games');
        self::assertSame('10:00-11:30', $stages[0][0]['times']);

        [$first, $second] = $stages[1][0]['brackets'];
        self::assertSame(1, $first['number'], 'by game number');
        self::assertSame(['Charlie', 'Delta'], array_column($first['teams'], 'name'), 'not played: in their order');
        self::assertSame(['Bravo', 'Alpha'], array_column($second['teams'], 'name'), 'the winner first');
        self::assertSame([true, false], array_column($second['teams'], 'winner'));
        self::assertSame(['3', '1'], array_column($second['teams'], 'score'));
    }

    public function testALeagueIsDrawnByGamedayInDateOrder(): void
    {
        $gamedays = SchemaLayout::gamedays([
            [...self::phase(1, 'Journée 2', 'C', [self::game(1, 'Alpha', 'Bravo', '0', '2')]), 'dateDebut' => '2026-05-02', 'dateFin' => '2026-05-03'],
            [...self::phase(1, 'Journée 1', 'C', []), 'dateDebut' => '2026-04-01', 'dateFin' => '2026-04-01'],
        ]);

        self::assertSame(['Journée 1', 'Journée 2'], array_column($gamedays, 'title'));
        self::assertSame('02/05/2026 - 03/05/2026', $gamedays[1]['dates']);
        self::assertSame('01/04/2026', $gamedays[0]['dates']);
        self::assertSame('Lacville (33)', $gamedays[1]['place']);
        self::assertSame('B', $gamedays[1]['games'][0]['winner']);
    }

    /**
     * @param list<array<string, mixed>>      $games
     * @param list<array<string, mixed>>|null $ranking
     *
     * @return array<string, mixed>
     */
    private static function phase(int $stage, string $name, string $type, array $games, ?array $ranking = null): array
    {
        return [
            'etape' => $stage, 'phase' => $name, 'nom' => '', 'type' => $type, 'nbequipes' => 4, 'nbMatchs' => count($games),
            'startTime' => '10:00:00', 'endTime' => '11:30:00', 'ranking' => $ranking, 'poolTeams' => null, 'matches' => $games,
            'dateDebut' => '2026-05-01', 'dateFin' => '2026-05-01', 'lieu' => 'Lacville', 'departement' => '33',
        ];
    }

    /** @return array<string, mixed> */
    private static function game(int $number, string $home, string $away, ?string $scoreA, ?string $scoreB): array
    {
        return ['numeroOrdre' => $number, 'equipeA' => $home, 'equipeB' => $away, 'scoreA' => $scoreA, 'scoreB' => $scoreB];
    }
}
