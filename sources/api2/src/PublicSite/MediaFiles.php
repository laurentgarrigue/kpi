<?php

namespace App\PublicSite;

/**
 * Visuels des clubs et des équipes, cherchés dans les médias montés sous {legacy}/img/ (MEDIA_STORAGE.md), avec
 * les règles des pages legacy (API_PUBLIC_TRANSVERSE.md § 3.4 et § 3.5). Les chemins renvoyés sont relatifs à
 * /img/, comme les autres visuels des endpoints publics.
 */
class MediaFiles
{
    /** Couleurs : de l'année courante à année − 3, puis le fichier sans année (kpequipes.php). */
    private const COLORS_YEARS_BACK = 3;

    /** Photo d'équipe : de l'année courante à année − 5 (kpequipes.php). */
    private const PHOTO_YEARS_BACK = 5;

    public function __construct(private readonly string $legacyDocumentRoot)
    {
    }

    /** Logo du club, sinon drapeau de la nation (3 premiers caractères du code), sinon null (kpclassements.php). */
    public function clubLogo(string $clubCode): ?string
    {
        foreach (['KIP/logo/' . $clubCode . '-logo.png', 'Nations/' . substr($clubCode, 0, 3) . '.png'] as $path) {
            if ($this->exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /** @return array{image: string, season: ?string}|null */
    public function teamColors(int $teamNumber, int $currentYear): ?array
    {
        $dated = $this->latestDated(fn (int $year): string => sprintf('KIP/colors/%d-%d-colors.png', $teamNumber, $year), $currentYear, self::COLORS_YEARS_BACK);
        if ($dated !== null) {
            return $dated;
        }
        $undated = sprintf('KIP/colors/%d-colors.png', $teamNumber);

        return $this->exists($undated) ? ['image' => $undated, 'season' => null] : null;
    }

    /**
     * Photo d'équipe (Q-P3-3 : maintenue jusqu'à l'étude RGPD).
     *
     * @return array{image: string, season: ?string}|null
     */
    public function teamPhoto(int $teamNumber, int $currentYear): ?array
    {
        return $this->latestDated(fn (int $year): string => sprintf('KIP/teams/%d-%d-team.jpg', $teamNumber, $year), $currentYear, self::PHOTO_YEARS_BACK);
    }

    /**
     * @param callable(int): string $path
     *
     * @return array{image: string, season: string}|null
     */
    private function latestDated(callable $path, int $currentYear, int $yearsBack): ?array
    {
        for ($year = $currentYear; $year >= $currentYear - $yearsBack; --$year) {
            if ($this->exists($path($year))) {
                return ['image' => $path($year), 'season' => (string) $year];
            }
        }

        return null;
    }

    private function exists(string $path): bool
    {
        return is_file($this->legacyDocumentRoot . '/img/' . $path);
    }
}
