<?php

namespace App\PublicSite\Ics;

/**
 * Écrit un calendrier iCalendar (RFC 5545) d'événements en journée entière (API_PUBLIC_TRANSVERSE.md § 3.2).
 * Fonctions pures : testées unitairement. Lignes terminées par CRLF, repliées à 75 octets, texte échappé.
 */
final class IcsCalendar
{
    public const CONTENT_TYPE = 'text/calendar; charset=utf-8';

    private const PRODID = '-//kayak-polo.info//Site public//FR';

    private const LINE_OCTETS = 75;

    /**
     * @param list<IcsEvent> $events
     */
    public static function render(string $name, array $events, \DateTimeImmutable $now): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::escape($name),
            'X-WR-TIMEZONE:Europe/Paris',
        ];
        $stamp = self::utc($now);
        foreach ($events as $event) {
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:' . $event->uid;
            $lines[] = 'DTSTAMP:' . $stamp;
            $lines[] = 'DTSTART;VALUE=DATE:' . $event->start->format('Ymd');
            // DTEND est exclusif : le lendemain du dernier jour (RFC 5545 § 3.6.1).
            $lines[] = 'DTEND;VALUE=DATE:' . $event->end->modify('+1 day')->format('Ymd');
            $lines[] = 'SUMMARY:' . self::escape($event->summary);
            if ($event->location !== null && $event->location !== '') {
                $lines[] = 'LOCATION:' . self::escape($event->location);
            }
            if ($event->url !== null) {
                $lines[] = 'URL:' . $event->url;
            }
            if ($event->lastModified !== null) {
                $lines[] = 'LAST-MODIFIED:' . self::utc($event->lastModified);
            }
            $lines[] = 'TRANSP:TRANSPARENT';
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        return implode('', array_map(static fn (string $line): string => self::fold($line) . "\r\n", $lines));
    }

    /** Échappement des valeurs TEXT : antislash, point-virgule, virgule, retours à la ligne (§ 3.3.11). */
    public static function escape(string $text): string
    {
        // Certains libellés legacy sont stockés avec des entités HTML : décodées AVANT l'échappement.
        $text = trim(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'], $text);
    }

    /** Replie une ligne à 75 octets sans couper un caractère UTF-8 ; suite précédée d'une espace (§ 3.1). */
    public static function fold(string $line): string
    {
        if (strlen($line) <= self::LINE_OCTETS) {
            return $line;
        }

        $parts = [];
        $current = '';
        $limit = self::LINE_OCTETS;
        foreach (mb_str_split($line) as $char) {
            if (strlen($current) + strlen($char) > $limit) {
                $parts[] = $current;
                $current = '';
                // Les lignes de continuation commencent par une espace, qui compte dans les 75 octets.
                $limit = self::LINE_OCTETS - 1;
            }
            $current .= $char;
        }
        $parts[] = $current;

        return implode("\r\n ", $parts);
    }

    private static function utc(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }
}
