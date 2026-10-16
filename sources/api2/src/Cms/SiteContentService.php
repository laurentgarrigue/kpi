<?php

namespace App\Cms;

use App\Cms\Dto\AlbumView;
use App\Cms\Dto\HomeBlock;
use App\Cms\Dto\MediaView;
use App\Cms\Dto\MenuEntry;
use Psr\Clock\ClockInterface;

/**
 * Contenus transverses du site public : menu « Le kayak-polo », blocs d'accueil, albums, redirections, RSS et plan
 * du site (FEATURE_CMS.md § 5.1, PAGE_CONTENT.md § 3–4).
 */
final class SiteContentService
{
    public const RSS_ITEMS = 20;
    private const DEFAULT_LATEST_NEWS = 3;
    private const ALERT_LEVELS = ['info', 'important'];

    public function __construct(
        private readonly SiteRepository $site,
        private readonly ArticleRepository $articles,
        private readonly MediaRepository $media,
        private readonly NewsService $news,
        private readonly PageService $pages,
        private readonly ClockInterface $clock,
        private readonly string $publicSiteUrl,
    ) {
    }

    /** @return list<MenuEntry> entrées dont la cible est publique ; un parent sans cible ni enfant visible est masqué */
    public function menu(string $language): array
    {
        $tree = $this->pages->tree();
        $now = $this->now();
        $byParent = [];
        foreach ($this->site->menuItems() as $item) {
            $byParent[$item['parent_id'] === null ? 0 : (int) $item['parent_id']][] = $item;
        }
        // Entrées publiées d'un niveau, avec leurs sous-entrées publiées (jusqu'à CmsRules::MENU_MAX_DEPTH niveaux).
        $build = function (int $parent) use (&$build, $byParent, $tree, $now, $language): array {
            return array_values(array_filter(array_map(
                fn (array $item): ?MenuEntry => $this->menuEntry($item, $build((int) $item['id']), $tree, $now, $language),
                $byParent[$parent] ?? [],
            )));
        };

        return $build(0);
    }

    /** @return list<HomeBlock> blocs actifs et non vides, dans l'ordre (PAGE_CONTENT.md § 4, CNT-05, CNT-06) */
    public function home(string $language): array
    {
        $blocks = [];
        foreach ($this->site->homeBlocks() as $row) {
            if ((int) $row['enabled'] !== 1) {
                continue;
            }
            $config = json_decode((string) $row['config'], true) ?: [];
            $block = match ((string) $row['kind']) {
                'alert' => $this->alert($config, $language),
                'featured' => $this->articlesBlock('featured', $this->news->byIds(array_map('intval', $config['articles'] ?? []), $language)),
                'latest_news' => $this->articlesBlock('latest_news', $this->news->latest((int) ($config['count'] ?? self::DEFAULT_LATEST_NEWS), $language)),
                'partners' => $this->partners($config, $language),
                default => null,
            };
            if ($block !== null) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    public function album(string $slug, string $language): ?AlbumView
    {
        $album = $this->media->findAlbumBySlug($slug);
        if ($album === null) {
            return null;
        }

        return new AlbumView(
            slug: (string) $album['slug'],
            title: CmsRules::localized($album, 'title', $language),
            media: array_map(static fn (array $row): MediaView => MediaView::fromRow($row, $language), $this->media->albumMedia((int) $album['id'])),
        );
    }

    /** @return array{to: string, code: int}|null redirection d'un ancien chemin (compte les accès) */
    public function redirect(string $path): ?array
    {
        $row = $this->site->redirectFor($path);
        if ($row === null) {
            return null;
        }
        $this->site->countRedirectHit((int) $row['id']);

        return ['to' => (string) $row['to_path'], 'code' => (int) $row['code']];
    }

    /** Flux RSS 2.0 des derniers articles publiés (CMS-08). */
    public function rss(string $language): string
    {
        $base = rtrim($this->publicSiteUrl, '/') . ($language === 'en' ? '/en' : '');
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('rss');
        $xml->writeAttribute('version', '2.0');
        $xml->writeAttribute('xmlns:atom', 'http://www.w3.org/2005/Atom');
        $xml->startElement('channel');
        $xml->writeElement('title', $language === 'en' ? 'kayak-polo.info — News' : 'kayak-polo.info — Actualités');
        $xml->writeElement('link', $base . '/news');
        $xml->writeElement('description', $language === 'en' ? 'Canoe polo news in France' : 'Actualités du kayak-polo en France');
        $xml->writeElement('language', $language === 'en' ? 'en-GB' : 'fr-FR');
        $xml->startElement('atom:link');
        $xml->writeAttribute('href', rtrim($this->publicSiteUrl, '/') . '/rss.xml' . ($language === 'en' ? '?lang=en' : ''));
        $xml->writeAttribute('rel', 'self');
        $xml->writeAttribute('type', 'application/rss+xml');
        $xml->endElement();
        foreach ($this->news->latest(self::RSS_ITEMS, $language) as $item) {
            $link = $base . '/news/' . $item->slug;
            $xml->startElement('item');
            $xml->writeElement('title', $item->title);
            $xml->writeElement('link', $link);
            $xml->startElement('guid');
            $xml->writeAttribute('isPermaLink', 'true');
            $xml->text($link);
            $xml->endElement();
            $xml->writeElement('pubDate', (new \DateTimeImmutable($item->publishedAt))->format(\DateTimeInterface::RSS));
            $xml->writeElement('description', $item->lead);
            foreach ($item->categories as $category) {
                $xml->writeElement('category', $category->label);
            }
            $xml->endElement();
        }
        $xml->endElement();
        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }

    /** @return list<array{path: string, updated_at: string}> chemins publics des articles et pages (CNT-07) */
    public function sitemapEntries(): array
    {
        $entries = [];
        foreach ($this->articles->findPublic($this->now(), null, null, null, 10000, 0) as $row) {
            $entries[] = ['path' => '/news/' . $row['slug'], 'updated_at' => NewsService::isoDate((string) $row['updated_at'])];
        }
        $tree = $this->pages->tree();
        foreach ($tree->publicPages() as $page) {
            $entries[] = ['path' => $tree->path((int) $page['id']), 'updated_at' => NewsService::isoDate((string) $page['updated_at'])];
        }

        return $entries;
    }

    /**
     * @param array<string, mixed>   $item
     * @param list<MenuEntry>        $children
     */
    private function menuEntry(array $item, array $children, PageTree $tree, string $now, string $language): ?MenuEntry
    {
        $label = CmsRules::localized($item, 'label', $language);
        $ref = (string) $item['target_ref'];
        $path = match ((string) $item['target_kind']) {
            'page' => $tree->isPublic((int) $ref) ? $tree->path((int) $ref) : null,
            'article' => $this->publicArticlePath((int) $ref, $now),
            'url' => $ref,
            default => null,
        };
        if ($path === null) {
            return $children === [] ? null : new MenuEntry($label, '', false, $children);
        }

        return new MenuEntry($label, $path, $item['target_kind'] === 'url', $children);
    }

    private function publicArticlePath(int $id, string $now): ?string
    {
        $rows = $this->articles->findPublicByIds([$id], $now);

        return $rows === [] ? null : '/news/' . $rows[0]['slug'];
    }

    /** @param array<string, mixed> $config */
    private function alert(array $config, string $language): ?HomeBlock
    {
        $text = (string) ($config['text_' . $language] ?? '') ?: (string) ($config['text_fr'] ?? '');
        $today = substr($this->now(), 0, 10);
        $from = $config['from'] ?? null;
        $to = $config['to'] ?? null;
        if ($text === '' || ($from !== null && $today < $from) || ($to !== null && $today > $to)) {
            return null;
        }
        $level = in_array($config['level'] ?? null, self::ALERT_LEVELS, true) ? $config['level'] : 'info';

        return new HomeBlock('alert', ['level' => $level, 'text' => $text]);
    }

    /** @param list<\App\Cms\Dto\NewsSummary> $articles */
    private function articlesBlock(string $kind, array $articles): ?HomeBlock
    {
        return $articles === [] ? null : new HomeBlock($kind, ['articles' => $articles]);
    }

    /** @param array<string, mixed> $config */
    private function partners(array $config, string $language): ?HomeBlock
    {
        $partners = is_array($config['partners'] ?? null) ? $config['partners'] : [];
        $logos = $this->media->findByIds(array_map(static fn (array $partner): int => (int) ($partner['media_id'] ?? 0), $partners));
        $items = array_map(static fn (array $partner): array => [
            'name' => (string) ($partner['name'] ?? ''),
            'url' => (string) ($partner['url'] ?? ''),
            'logo' => isset($logos[(int) ($partner['media_id'] ?? 0)]) ? MediaView::fromRow($logos[(int) $partner['media_id']], $language) : null,
        ], $partners);

        return $items === [] ? null : new HomeBlock('partners', ['partners' => $items]);
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:s');
    }
}
