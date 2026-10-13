<?php

namespace App\Cms;

/** Règles communes du module éditorial (FEATURE_CMS.md) : statuts, langues, slugs, visibilité. */
final class CmsRules
{
    /** Niveaux du menu « Le kayak-polo » : entrée, sous-entrée, sous-sous-entrée (PAGE_CONTENT.md § 3). */
    public const MENU_MAX_DEPTH = 3;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUSES = [self::STATUS_DRAFT, self::STATUS_PUBLISHED, self::STATUS_ARCHIVED];

    public const LANGUAGES = ['fr', 'en'];
    public const DEFAULT_LANGUAGE = 'fr';

    public const SLUG_MAX_LENGTH = 150;
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /** Premiers segments des routes du site et des services voisins : jamais un slug de page (§ 6). */
    public const RESERVED_SLUGS = [
        'news', 'competitions', 'calendar', 'events', 'groups', 'history', 'teams', 'clubs', 'search', 'forms',
        'media', 'img', 'api', 'api2', 'admin', 'admin2', 'en', 'fr', 'healthz', 'robots.txt', 'sitemap.xml',
        'rss.xml', 'games', '_nuxt', '_payload.json', '_internal',
    ];

    /** Langue demandée, `fr` si absente ou inconnue. */
    public static function language(?string $language): string
    {
        return in_array($language, self::LANGUAGES, true) ? $language : self::DEFAULT_LANGUAGE;
    }

    /**
     * Valeur localisée d'une colonne `{field}_{lang}` : la version anglaise vide est remplacée par la française
     * (CMS-05).
     *
     * @param array<string, mixed> $row
     */
    public static function localized(array $row, string $field, string $language): string
    {
        $value = (string) ($row[$field . '_' . $language] ?? '');

        return $value !== '' ? $value : (string) ($row[$field . '_' . self::DEFAULT_LANGUAGE] ?? '');
    }

    /** Slug à partir d'un titre : minuscules sans accents, mots séparés par des tirets. */
    public static function slugify(string $text): string
    {
        $ascii = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $text);
        $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', (string) $ascii), '-');

        return substr($slug, 0, self::SLUG_MAX_LENGTH);
    }

    public static function isValidSlug(string $slug): bool
    {
        return strlen($slug) <= self::SLUG_MAX_LENGTH && preg_match(self::SLUG_PATTERN, $slug) === 1;
    }

    public static function isReservedSlug(string $slug): bool
    {
        return in_array($slug, self::RESERVED_SLUGS, true);
    }

    /** Un article est public s'il est publié et que sa date de publication est passée (CMS-01). */
    public static function isPublic(string $status, ?string $publishedAt, \DateTimeImmutable $now): bool
    {
        return $status === self::STATUS_PUBLISHED && $publishedAt !== null && $publishedAt <= $now->format('Y-m-d H:i:s');
    }
}
