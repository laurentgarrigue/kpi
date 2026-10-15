<?php

namespace App\PublicSite;

/**
 * Règles des endpoints publics transverses reprises du legacy (API_PUBLIC_TRANSVERSE.md § 3).
 * Fonctions pures : testées unitairement.
 */
final class PublicSiteRules
{
    public const SEARCH_MIN_LENGTH = 2;

    public const SEARCH_MAX_LENGTH = 50;

    /**
     * Sections filtrables du calendrier (kp_groupe.section), dans l'ordre d'affichage : internationales, nationales,
     * régionales, tournois internationaux, continents, divers.
     */
    public const SECTIONS = [1, 2, 3, 4, 5, 100];

    /** Section des compétitions dont le groupe n'a pas de section (calendrier : « Divers »). */
    public const DEFAULT_SECTION = 100;

    /**
     * Libellé d'une journée dans le calendrier et l'abonnement, comme le legacy (json-events.php) :
     * « nom de la journée - lieu (département) ». Sans nom, le titre de la compétition le remplace ; le lieu et
     * le département absents sont omis.
     */
    public static function gamedayLabel(?string $name, ?string $place, ?string $department, string $fallback): string
    {
        $decode = static fn (?string $value): ?string => self::nullIfEmpty($value === null ? null : html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $label = $decode($name) ?? $fallback;
        $place = $decode($place);
        $department = $decode($department);

        return $label . ($place === null ? '' : ' - ' . $place) . ($department === null ? '' : ' (' . $department . ')');
    }

    /**
     * Position « lat, lng » de kp_club.Coord ; null si absente ou hors bornes.
     *
     * @return array{lat: float, lng: float}|null
     */
    public static function position(?string $coord): ?array
    {
        if ($coord === null || !preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*,\s*(-?\d+(?:\.\d+)?)\s*$/', $coord, $matches)) {
            return null;
        }
        $lat = (float) $matches[1];
        $lng = (float) $matches[2];
        if (abs($lat) > 90 || abs($lng) > 180 || ($lat === 0.0 && $lng === 0.0)) {
            return null;
        }

        return ['lat' => $lat, 'lng' => $lng];
    }

    /** Rôle public d'un membre de l'équipe : C = capitaine, E = entraîneur (kpequipes.tpl). */
    public static function role(?string $captain): ?string
    {
        return match ($captain) {
            'C' => 'captain',
            'E' => 'coach',
            default => null,
        };
    }

    /**
     * Terme de recherche normalisé (espaces multiples réduits), ou null s'il est hors bornes.
     */
    public static function searchTerm(?string $query): ?string
    {
        $term = trim(preg_replace('/\s+/u', ' ', (string) $query) ?? '');
        $length = mb_strlen($term);

        return $length < self::SEARCH_MIN_LENGTH || $length > self::SEARCH_MAX_LENGTH ? null : $term;
    }

    /**
     * Motifs LIKE « contient » pour un terme : tel quel, et avec des tirets à la place des espaces
     * (« Saint Malo » trouve « Saint-Malo », comme searchEquipes.php). Les jokers SQL sont échappés.
     *
     * @return array{string, string}
     */
    public static function containsPatterns(string $term): array
    {
        $escaped = addcslashes($term, '\\%_');

        return ['%' . $escaped . '%', '%' . str_replace(' ', '-', $escaped) . '%'];
    }

    /** Motif LIKE « commence par » (codes de club, de compétition). */
    public static function prefixPattern(string $term): string
    {
        return addcslashes($term, '\\%_') . '%';
    }

    public static function nullIfEmpty(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' ? null : $value;
    }

    /**
     * Fusionne les journées d'une même compétition aux mêmes dates et lieu (phases d'une coupe), comme
     * json-events.php. Les lignes doivent être triées ; la première de chaque groupe est conservée.
     *
     * @param list<array<string, mixed>> $rows lignes avec Code_saison, Code_competition, Date_debut, Date_fin, Lieu
     *
     * @return list<array<string, mixed>>
     */
    public static function mergeGamedays(array $rows): array
    {
        $merged = [];
        foreach ($rows as $row) {
            $key = implode('|', [$row['Code_saison'], $row['Code_competition'], $row['Date_debut'], $row['Date_fin'], mb_strtolower((string) $row['Lieu'])]);
            $merged[$key] ??= $row;
        }

        return array_values($merged);
    }
}
