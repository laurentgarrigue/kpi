<?php

namespace App\Tests\Unit\PublicResults;

use App\PublicResults\MatchLabelParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MatchLabelParserTest extends TestCase
{
    /** @return iterable<string, array{string, array<int, string>}> */
    public static function labels(): iterable
    {
        yield 'winner / loser' => ['[V11-P12]', [0 => '(Winner game #11)', 1 => '(Loser game #12)']];
        yield 'english codes' => ['[W3/L4]', [0 => '(Winner game #3)', 1 => '(Loser game #4)']];
        yield 'teams' => ['Match [T1-D2]', [0 => '(Team 1)', 1 => '(Team 2)']];
        yield 'group ranks' => ['[1A-2B]', [0 => '(1st Group A)', 1 => '(2nd Group B)']];
        yield 'fourth of a group' => ['[4C-3D]', [0 => '(4th Group C)', 1 => '(3rd Group D)']];
        yield 'no brackets' => ['M12', []];
        yield 'empty brackets' => ['[]', []];
    }

    /** @param array<int, string> $expected */
    #[DataProvider('labels')]
    public function testParsesPlaceholderLabels(string $label, array $expected): void
    {
        self::assertSame($expected, (new MatchLabelParser())->parse($label));
    }
}
