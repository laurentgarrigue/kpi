<?php

namespace App\Tests\Unit\Scoring;

use App\Service\ScoringLiveService;
use PHPUnit\Framework\TestCase;

/**
 * `started_at` is stored as a naive DATETIME(3) written in api2's PHP timezone. Readers in
 * another timezone (an OBS PC in Paris) must not re-interpret it: the state ships it as an
 * absolute instant, epoch milliseconds (PAGE_INCRUSTATION.md, clock interpolation).
 */
final class ClockInstantTest extends TestCase
{
    private string $previousTimezone;

    protected function setUp(): void
    {
        $this->previousTimezone = date_default_timezone_get();
    }

    protected function tearDown(): void
    {
        date_default_timezone_set($this->previousTimezone);
    }

    public function testAStartInstantWrittenInUtcIsAnAbsoluteEpoch(): void
    {
        date_default_timezone_set('UTC');

        self::assertSame(1794228021851, ScoringLiveService::instantMs('2026-11-09 12:40:21.851'));
    }

    public function testTheInstantFollowsTheTimezoneTheValueWasWrittenIn(): void
    {
        date_default_timezone_set('Europe/Paris');

        self::assertSame(1794228021851, ScoringLiveService::instantMs('2026-11-09 13:40:21.851'));
    }

    public function testAStoppedClockHasNoInstant(): void
    {
        self::assertNull(ScoringLiveService::instantMs(null));
        self::assertNull(ScoringLiveService::instantMs(''));
    }
}
