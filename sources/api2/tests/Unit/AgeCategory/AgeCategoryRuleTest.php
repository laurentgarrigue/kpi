<?php

namespace App\Tests\Unit\AgeCategory;

use App\AgeCategory\AgeCategoryRule;
use App\Tests\Support\FfckAgeCategoryTable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Catégorie d'âge (CATEGORIE_AGE.md, ADR 0001): one rule, from the birth year and the season of the competition. */
final class AgeCategoryRuleTest extends TestCase
{
    private const SEASON = '2026';

    private AgeCategoryRule $rule;

    protected function setUp(): void
    {
        $this->rule = new AgeCategoryRule(new FfckAgeCategoryTable());
    }

    /** @return iterable<string, array{int, string}> lower and upper bound of every category (story 6) */
    public static function bounds(): iterable
    {
        foreach ([['POU', 9, 10], ['BEN', 11, 12], ['MIN', 13, 14], ['CAD', 15, 16], ['JUN', 17, 18], ['SEN', 19, 34],
            ['V1', 35, 39], ['V2', 40, 44], ['V3', 45, 49], ['V4', 50, 54], ['V5', 55, 59], ['V6', 60, 64],
            ['V7', 65, 69], ['V8', 70, 74], ['V9', 75, 120]] as [$code, $min, $max]) {
            yield "$code min" => [$min, $code];
            yield "$code max" => [$max, $code];
        }
    }

    #[DataProvider('bounds')]
    public function testCategoryKeepsTheBoundsOfTheCategoryTable(int $age, string $expected): void
    {
        $birthDate = sprintf('%d-07-14', (int) self::SEASON - $age);

        self::assertSame($expected, $this->rule->categoryFor($birthDate, self::SEASON)?->code);
    }

    public function testSamePlayerIsV3In2020AndV4In2025(): void
    {
        self::assertSame('V3', $this->rule->categoryFor('1972-05-03', '2020')?->code);
        self::assertSame('V4', $this->rule->categoryFor('1972-05-03', '2025')?->code);
    }

    /** @return iterable<string, array{?string}> */
    public static function unknownBirthDates(): iterable
    {
        yield 'absent' => [null];
        yield 'empty' => [''];
        yield 'conventional 1901' => ['1901-01-01'];
        yield 'conventional 0000' => ['0000-00-00'];
    }

    #[DataProvider('unknownBirthDates')]
    public function testUnknownBirthDateGivesNoCategory(?string $birthDate): void
    {
        self::assertTrue(AgeCategoryRule::isUnknownBirthDate($birthDate));
        self::assertNull($this->rule->categoryFor($birthDate, self::SEASON));
    }

    public function testUnknownBirthDateGivesNoCategoryEvenWhenTheTableWouldMatchItsAge(): void
    {
        // 1901 is 125 years old in 2026; in 1990 it would be 89, i.e. V9 if the date were taken at face value
        self::assertNull($this->rule->categoryFor('1901-01-01', '1990'));
        self::assertFalse(AgeCategoryRule::isUnknownBirthDate('1972-05-03'));
    }

    /** @return iterable<string, array{string, string, bool}> birth date, season, plausible (story 14) */
    public static function plausibility(): iterable
    {
        yield '100 years old' => ['1926-12-31', '2026', true];
        yield '101 years old' => ['1925-01-01', '2026', false];
        yield '5 years old' => ['2021-01-01', '2026', true];
        yield '4 years old' => ['2022-06-30', '2026', false];
        yield 'counted on the given season' => ['1925-01-01', '2025', true];
        yield 'conventional 1901' => ['1901-01-01', '2026', false];
        yield 'conventional 0000' => ['0000-00-00', '2026', false];
    }

    #[DataProvider('plausibility')]
    public function testBirthDateIsPlausibleFrom5To100YearsOldInTheSeason(string $birthDate, string $season, bool $expected): void
    {
        self::assertSame($expected, AgeCategoryRule::isPlausibleBirthDate($birthDate, $season));
    }
}
