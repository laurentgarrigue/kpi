<?php

namespace App\Tests\Unit\Discipline;

use App\Discipline\SuspendedStatus;
use PHPUnit\Framework\TestCase;

/** Statut « Suspendu » (FEATURE_CARD_SUSPENSIONS.md S17, S18). */
final class SuspendedStatusTest extends TestCase
{
    public function testSus21OnlyProfilesOneAndTwoGiveOrRemoveTheSuspendedStatus(): void
    {
        self::assertTrue(SuspendedStatus::denies(3, '-', 'S'));
        self::assertTrue(SuspendedStatus::denies(7, 'S', 'C'));
        self::assertFalse(SuspendedStatus::denies(2, '-', 'S'));
        self::assertFalse(SuspendedStatus::denies(1, 'S', '-'));
        self::assertFalse(SuspendedStatus::denies(7, '-', 'C'));
        self::assertFalse(SuspendedStatus::denies(7, 'S', 'S'));
    }

    public function testSus20ASuspendedPlayerStaysOffTheMatchSheets(): void
    {
        self::assertSame(['A', 'X', 'S'], SuspendedStatus::OFF_MATCH_SHEET);
        self::assertSame("'A', 'X', 'S'", SuspendedStatus::offMatchSheetSql());
    }
}
