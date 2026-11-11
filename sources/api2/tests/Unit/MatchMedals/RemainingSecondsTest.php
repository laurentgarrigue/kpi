<?php

namespace App\Tests\Unit\MatchMedals;

use App\MatchMedals\MatchMedalRepository;
use PHPUnit\Framework\TestCase;

/** Reading kp_match_detail.Temps (remaining time on the clock, « 00:mm:ss »). */
final class RemainingSecondsTest extends TestCase
{
    public function testTheTimeIsTheRemainingMinutesAndSeconds(): void
    {
        self::assertSame(550, MatchMedalRepository::remainingSeconds('00:09:10'));
        self::assertSame(45, MatchMedalRepository::remainingSeconds('00:00:45'));
    }

    public function testMed03ZeroTimeMeansNotRecordedLikeAMissingTime(): void
    {
        // legacy sheets store « 00: » + the typed time: an empty time becomes 00:00:00
        self::assertNull(MatchMedalRepository::remainingSeconds('00:00:00'));
        self::assertNull(MatchMedalRepository::remainingSeconds(null));
        self::assertNull(MatchMedalRepository::remainingSeconds(''));
    }
}
