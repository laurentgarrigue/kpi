<?php

namespace App\Tests\Unit\Pdf\Presence;

use App\Pdf\Presence\PresencePlayer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Rows of the presence and control sheets, as the legacy pages printed them (PDF-DOC-01). */
final class PresencePlayerTest extends TestCase
{
    /** @return iterable<string, array{?string, ?string, ?string, string}> */
    public static function paddles(): iterable
    {
        yield 'kayak-polo level from green' => ['PAGV', 'PAGR', null, 'Verte'];
        yield 'equivalent whitewater level' => ['PAGJ', 'PAGBL', null, '(Bleue)'];
        yield 'sea level overrides whitewater' => [null, 'PAGBL', 'PAGN', '(Noire)'];
        yield 'yellow kayak-polo paddle' => ['PAGJ', null, null, 'Jaune'];
        yield 'nothing' => [null, null, null, ''];
    }

    #[DataProvider('paddles')]
    public function testPaddleLevel(?string $polo, ?string $whitewater, ?string $sea, string $expected): void
    {
        self::assertSame($expected, PresencePlayer::paddle($polo, $whitewater, $sea));
    }

    public function testLicenceShowsTheIcfNumberOfAProvisionalLicenceAndTheSeasonOfOrigin(): void
    {
        self::assertSame('123456', PresencePlayer::licence(123456, null, '2026', '2026'));
        self::assertSame('123456 (2025)', PresencePlayer::licence(123456, null, '2025', '2026'));
        self::assertSame('7788', PresencePlayer::licence(2000123, '7788', '2026', '2026'));
        self::assertSame('', PresencePlayer::licence(2000123, '0', '2026', '2026'));
    }

    public function testNamesRoleAndRefereeQualification(): void
    {
        self::assertSame('DUPONT-ÉLIE', PresencePlayer::lastName('Dupont-élie'));
        self::assertSame('Jean-Marc', PresencePlayer::firstName('JEAN-MARC'));
        $labels = ['role.C' => 'Captain', 'role.E' => 'Staff'];
        self::assertSame('', PresencePlayer::role('', $labels));
        self::assertSame('', PresencePlayer::role('-', $labels));
        self::assertSame('', PresencePlayer::role(null, $labels));
        self::assertSame('Captain', PresencePlayer::role('C', $labels));
        self::assertSame('Staff', PresencePlayer::role('E', $labels));
        self::assertSame('Z', PresencePlayer::role('Z', $labels), 'an unknown code stays as it is');
        self::assertSame('Nat-B', PresencePlayer::referee('Nat', 'B'));
        self::assertSame('Reg', PresencePlayer::referee('Reg', ''));
        self::assertSame('', PresencePlayer::referee(null, null));
    }

    public function testUnder21IsBornAfterTheYearSeasonMinus22(): void
    {
        self::assertTrue(PresencePlayer::isUnder21('2005-01-01', '2026'));
        self::assertFalse(PresencePlayer::isUnder21('2004-12-31', '2026'));
        self::assertFalse(PresencePlayer::isUnder21(null, '2026'));
        self::assertFalse(PresencePlayer::isUnder21('0000-00-00', '2026'));
    }
}
