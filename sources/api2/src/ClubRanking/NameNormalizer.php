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
}
