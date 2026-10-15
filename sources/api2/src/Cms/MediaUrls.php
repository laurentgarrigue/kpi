<?php

namespace App\Cms;

/**
 * Adresses publiques des médias éditoriaux (FEATURE_CMS.md § 5.3) : fichiers sous {legacy}/media/content/, servis
 * par le serveur legacy sous /media/content/. Chaque image a des variantes WebP de largeurs fixes, plus petites que
 * l'original, nommées `{nom}-{largeur}.webp` à côté du fichier.
 */
final class MediaUrls
{
    public const PUBLIC_PREFIX = '/media/content/';
    public const VARIANT_WIDTHS = [320, 768, 1280, 1920];

    public static function url(string $path): string
    {
        return self::PUBLIC_PREFIX . ltrim($path, '/');
    }

    /** Chemin (relatif à content/) de la variante d'une image. */
    public static function variantPath(string $path, int $width): string
    {
        $info = pathinfo($path);
        $directory = ($info['dirname'] ?? '.') === '.' ? '' : $info['dirname'] . '/';

        return sprintf('%s%s-%d.webp', $directory, $info['filename'], $width);
    }

    /** @return list<int> largeurs des variantes produites pour une image de cette largeur */
    public static function variantWidths(?int $width): array
    {
        return $width === null ? [] : array_values(array_filter(self::VARIANT_WIDTHS, static fn (int $variant): bool => $variant < $width));
    }

    public static function isImage(string $mime): bool
    {
        return str_starts_with($mime, 'image/');
    }
}
