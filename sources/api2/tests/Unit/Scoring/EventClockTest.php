<?php

namespace App\Tests\Unit\Scoring;

use App\Scoring\EventClock;
use PHPUnit\Framework\TestCase;

/**
 * Clock of an event: the Event Cache Manager's simulated clock when a worker runs for
 * the event, the real clock otherwise. The overlay program and the legacy cache must
 * pick matches on the same clock (PAGE_INCRUSTATION.md §6).
 */
final class EventClockTest extends TestCase
{
    private const WORKER_CONFIG = [
        'date_event' => '2026-06-20',
        'hour_event_initial' => '08:00:00',
        'offset_event' => 15,
        'created_at' => '2026-11-09 13:30:00',
    ];

    public function testAWorkerClockStartsAtTheInitialEventTime(): void
    {
        $clock = EventClock::fromWorkerConfig(self::WORKER_CONFIG, strtotime('2026-11-09 13:30:00'));

        self::assertSame('2026-06-20', $clock->date);
        self::assertSame('08:00', $clock->hour);
    }

    public function testAWorkerClockAdvancesWithRealTime(): void
    {
        $clock = EventClock::fromWorkerConfig(self::WORKER_CONFIG, strtotime('2026-11-09 14:05:00'));

        self::assertSame('2026-06-20', $clock->date);
        self::assertSame('08:35', $clock->hour);
    }

    public function testTheWorkingHourIncludesTheWarmUpOffset(): void
    {
        $clock = EventClock::fromWorkerConfig(self::WORKER_CONFIG, strtotime('2026-11-09 13:30:00'));

        self::assertSame('08:15', $clock->workHour);
    }

    public function testAWorkerClockRollsOverToTheNextMatchDay(): void
    {
        $clock = EventClock::fromWorkerConfig(self::WORKER_CONFIG, strtotime('2026-11-10 13:30:00'));

        self::assertSame('2026-06-21', $clock->date);
        self::assertSame('08:00', $clock->hour);
    }

    public function testTheRealClockHasNoOffset(): void
    {
        $clock = EventClock::real(strtotime('2026-11-09 13:30:00'));

        self::assertSame('2026-11-09', $clock->date);
        self::assertSame('13:30', $clock->hour);
        self::assertSame('13:30', $clock->workHour);
    }
}
