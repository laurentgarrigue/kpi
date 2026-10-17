<?php

namespace App\Tests\Unit\PublicSite;

use App\PublicSite\Ics\IcsCalendar;
use App\PublicSite\Ics\IcsEvent;
use PHPUnit\Framework\TestCase;

/** API3-02 : iCalendar RFC 5545 (échappement, repli à 75 octets, journées entières, CRLF). */
final class IcsCalendarTest extends TestCase
{
    public function testEscapesTextValuesAfterDecodingHtmlEntities(): void
    {
        self::assertSame('Coupe\\, phase 1\; poule A\\\\B', IcsCalendar::escape('Coupe, phase 1; poule A\\B'));
        self::assertSame('Ligne 1\\nLigne 2', IcsCalendar::escape("Ligne 1\nLigne 2"));
        self::assertSame('Saint-Jean d\'Angély\\, 17', IcsCalendar::escape('Saint-Jean d&#039;Angély&#44; 17'));
    }

    public function testFoldsLongLinesAt75OctetsWithoutSplittingUtf8Characters(): void
    {
        $line = 'SUMMARY:' . str_repeat('é', 60);
        $folded = IcsCalendar::fold($line);

        foreach (explode("\r\n", $folded) as $index => $physical) {
            self::assertLessThanOrEqual(75, strlen($physical));
            self::assertTrue(mb_check_encoding($physical, 'UTF-8'), 'pas de caractère coupé');
            if ($index > 0) {
                self::assertStringStartsWith(' ', $physical);
            }
        }
        self::assertSame($line, str_replace("\r\n ", '', $folded));
        self::assertSame('SUMMARY:court', IcsCalendar::fold('SUMMARY:court'));
    }

    public function testRendersAllDayEventsWithExclusiveEndDate(): void
    {
        $event = new IcsEvent(
            IcsEvent::gamedayUid(42),
            new \DateTimeImmutable('2026-06-12'),
            new \DateTimeImmutable('2026-06-14'),
            'Nationale 1 — J3',
            'Lacville (33)',
            'https://beta.kayak-polo.info/competitions/2026/N1H',
        );
        $ics = IcsCalendar::render('Nationale 1 (2026)', [$event], new \DateTimeImmutable('2026-01-02 10:00:00', new \DateTimeZone('Europe/Paris')));

        self::assertStringStartsWith("BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:", $ics);
        self::assertStringContainsString("UID:gameday-42@kayak-polo.info\r\n", $ics);
        self::assertStringContainsString("DTSTAMP:20260102T090000Z\r\n", $ics);
        self::assertStringContainsString("DTSTART;VALUE=DATE:20260612\r\nDTEND;VALUE=DATE:20260615\r\n", $ics);
        self::assertStringContainsString("LOCATION:Lacville (33)\r\n", $ics);
        self::assertStringEndsWith("END:VEVENT\r\nEND:VCALENDAR\r\n", $ics);
    }
}
