<?php

namespace App\ClubRanking;

/**
 * Normalised form of a person's name, to match the free-text delegate / chief referee of a gameday with a licensee
 * (N-5): upper case, no accents, letters and single spaces only.
 */
final class NameNormalizer
{
    private const SEPARATORS = '/\s*(?:\/|;|,|&|\+|\bet\b)\s*/iu';

    public static function normalize(string $name): string
    {
        $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Upper()', $name);
        $letters = preg_replace('/[^A-Z]+/', ' ', is_string($ascii) ? $ascii : mb_strtoupper($name));

        return trim((string) $letters);
    }

    /**
     * The people named in a free-text field (« DUPONT Jean / MARTIN Paul »).
     *
     * @return list<string> raw names, trimmed, empty ones removed
     */
    public static function split(string $field): array
    {
        $parts = preg_split(self::SEPARATORS, $field) ?: [];

        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => self::normalize($part) !== ''));
    }

    /** The licence number written after a name, « Virginie BRACKEZ (26407) » → 26407. */
    public static function matric(string $name): ?int
    {
        return preg_match('/\(\s*(\d{1,9})\s*\)\s*$/', $name, $found) === 1 ? (int) $found[1] : null;
    }

    /** The name without its trailing « (licence number) ». */
    public static function withoutMatric(string $name): string
    {
        return trim(preg_replace('/\(\s*\d{1,9}\s*\)\s*$/', '', $name) ?? $name);
    }

    /** A club label as typed in a gameday: HTML entities decoded (« D&#039;AVRANCHES »), then normalised. */
    public static function normalizeLabel(string $label): string
    {
        return self::normalize(html_entity_decode($label, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
