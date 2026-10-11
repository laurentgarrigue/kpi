<?php

namespace App\Cms\Import;

/** Paramètres de la reprise WordPress (FEATURE_CMS.md § 8, décisions Q-P4-4 et Q-P4-8). */
final class ImportOptions
{
    /** Pages reprises (Q-P4-4). */
    public const PAGES = [2849, 2711, 2286, 2270, 2267, 1881, 1723, 1591, 1581, 1480, 1049, 987, 313, 1579];

    /** Pages à retravailler : reprises en brouillon (313 à rafraîchir, 1579 à réactualiser). */
    public const DRAFT_PAGES = [313, 1579];

    /** Seul formulaire Ninja Forms repris, comme exemple, non publié (Q-P4-8). */
    public const NINJA_FORM = 19;

    /**
     * @param list<int> $pageIds
     * @param list<int> $draftPageIds
     */
    public function __construct(
        public readonly string $prefix = 'wp_',
        public readonly int $articleCount = 50,
        public readonly array $pageIds = self::PAGES,
        public readonly array $draftPageIds = self::DRAFT_PAGES,
        public readonly ?int $ninjaFormId = self::NINJA_FORM,
        public readonly ?string $uploadsDir = null,
        public readonly string $siteUrl = 'https://www.kayak-polo.info',
        public readonly bool $dryRun = false,
    ) {
    }

    public function uploadsUrl(): string
    {
        return rtrim($this->siteUrl, '/') . '/wp-content/uploads/';
    }
}
