<?php

namespace App\Pdf;

/**
 * Which visuals head and foot a page (PDF_GOTENBERG.md § 8.4.1): the banner of the competition replaces every logo;
 * otherwise its logo goes with the logos of the charter when « KPI/FFCK » is ticked; without banner nor logo, a public
 * PDF always carries FFCK + CNA, an admin one KPI when ticked. Pure functions, unit-tested.
 */
final class PdfHeader
{
    /**
     * Logos of the charter shown in the header.
     *
     * @param array<string, mixed>|null $competition competition of the page, with its visuals (PdfCompetitions)
     *
     * @return list<string>
     */
    public static function charterLogos(PdfTheme $theme, ?array $competition): array
    {
        $charter = PdfAssets::LOGOS[$theme->value];
        if (($competition['banner'] ?? null) !== null) {
            return [];
        }
        $ticked = (bool) ($competition['charter'] ?? true);
        if (($competition['logo'] ?? null) !== null || $theme === PdfTheme::Admin) {
            return $ticked ? $charter : [];
        }

        return $charter;
    }

    /**
     * The competition logo, shown when there is no banner.
     *
     * @param array<string, mixed>|null $competition
     */
    public static function competitionLogo(?array $competition): ?string
    {
        return ($competition['banner'] ?? null) === null ? ($competition['logo'] ?? null) : null;
    }

    /**
     * The sponsor of the footer, as a data URI: Chromium renders the footer apart from the page and only takes
     * inline images there.
     *
     * @param array<string, mixed>|null $competition
     * @param array<string, string>     $images      images of the page, by name
     */
    public static function sponsorDataUri(?array $competition, array $images): ?string
    {
        $sponsor = $competition['sponsor'] ?? null;
        if ($sponsor === null || !isset($images[$sponsor])) {
            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($images[$sponsor]) ?: 'image/png';

        return 'data:' . $mime . ';base64,' . base64_encode($images[$sponsor]);
    }
}
