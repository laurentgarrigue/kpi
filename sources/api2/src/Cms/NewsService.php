<?php

namespace App\Cms;

use App\Cms\Dto\CategoryView;
use App\Cms\Dto\MediaView;
use App\Cms\Dto\NewsArticle;
use App\Cms\Dto\NewsSummary;
use Psr\Clock\ClockInterface;

/** Actualités publiques (FEATURE_CMS.md § 5.1, PAGE_NEWS.md). */
final class NewsService
{
    public const PAGE_SIZE = 12;

    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly CategoryRepository $categories,
        private readonly MediaRepository $media,
        private readonly ArticleLinks $links,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return list<CategoryView> */
    public function categories(string $language): array
    {
        return array_map(static fn (array $row): CategoryView => self::category($row, $language), $this->categories->all());
    }

    /**
     * Page de la liste, du plus récent au plus ancien ; `null` si la catégorie est inconnue (CMS-13).
     *
     * @param array{kind: string, ref: string}|null $link
     *
     * @return array{items: list<NewsSummary>, page: int, pages: int, total: int}|null
     */
    public function list(int $page, ?string $categorySlug, ?array $link, string $language, int $pageSize = self::PAGE_SIZE): ?array
    {
        $categoryId = null;
        if ($categorySlug !== null) {
            $categoryId = $this->categories->idOfSlug($categorySlug);
            if ($categoryId === null) {
                return null;
            }
        }
        $now = $this->now();
        $total = $this->articles->countPublic($now, $categoryId, $link['kind'] ?? null, $link['ref'] ?? null);
        $rows = $this->articles->findPublic($now, $categoryId, $link['kind'] ?? null, $link['ref'] ?? null, $pageSize, ($page - 1) * $pageSize);

        return [
            'items' => $this->summaries($rows, $language),
            'page' => $page,
            'pages' => max(1, (int) ceil($total / $pageSize)),
            'total' => $total,
        ];
    }

    /** @return list<NewsSummary> */
    public function latest(int $count, string $language): array
    {
        return $this->summaries($this->articles->findPublic($this->now(), null, null, null, $count, 0), $language);
    }

    /**
     * @param list<int> $ids
     *
     * @return list<NewsSummary> articles publics parmi ces ids, dans l'ordre donné
     */
    public function byIds(array $ids, string $language): array
    {
        return $this->summaries($this->articles->findPublicByIds($ids, $this->now()), $language);
    }

    /** @return list<NewsSummary> */
    public function search(string $pattern, int $limit, string $language): array
    {
        return $this->summaries($this->articles->searchPublic($pattern, $this->now(), $limit), $language);
    }

    /** Article public, ou non publié avec un jeton de prévisualisation (`$previewAllowed`). */
    public function article(string $slug, string $language, ?callable $previewAllowed = null): ?NewsArticle
    {
        $now = $this->now();
        $row = $this->articles->findPublicBySlug($slug, $now);
        $preview = false;
        if ($row === null && $previewAllowed !== null) {
            $candidate = $this->articles->findBySlug($slug);
            if ($candidate !== null && $previewAllowed((int) $candidate['id'])) {
                $row = $candidate;
                $preview = true;
            }
        }
        if ($row === null) {
            return null;
        }

        $id = (int) $row['id'];
        $cover = $row['cover_media_id'] === null ? null : $this->media->find((int) $row['cover_media_id']);
        $links = array_values(array_filter(array_map(
            fn (array $link): ?array => $this->links->resolve($link['kind'], $link['ref']),
            $this->articles->linksOf($id),
        )));
        $neighbour = fn (bool $newer): ?array => $preview ? null : self::reference($this->articles->findPublicNeighbour($row, $now, $newer), $language);

        return new NewsArticle(
            slug: (string) $row['slug'],
            title: CmsRules::localized($row, 'title', $language),
            lead: CmsRules::localized($row, 'lead', $language),
            body: CmsRules::localized($row, 'body', $language),
            cover: $cover === null ? null : MediaView::fromRow($cover, $language),
            publishedAt: $row['published_at'] === null ? null : self::isoDate((string) $row['published_at']),
            updatedAt: self::isoDate((string) $row['updated_at']),
            signature: (string) $row['signature'],
            categories: array_map(static fn (array $category): CategoryView => self::category($category, $language), $this->articles->categoriesOf([$id])[$id] ?? []),
            links: $links,
            seo: [
                'title' => CmsRules::localized($row, 'seo_title', $language),
                'description' => CmsRules::localized($row, 'seo_description', $language),
            ],
            previous: $neighbour(false),
            next: $neighbour(true),
            preview: $preview,
        );
    }

    /**
     * @param list<array<string, mixed>> $rows
     *
     * @return list<NewsSummary>
     */
    public function summaries(array $rows, string $language): array
    {
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $categories = $this->articles->categoriesOf($ids);
        $covers = $this->media->findByIds(array_map(static fn (array $row): int => (int) $row['cover_media_id'], $rows));

        return array_map(static fn (array $row): NewsSummary => new NewsSummary(
            slug: (string) $row['slug'],
            title: CmsRules::localized($row, 'title', $language),
            lead: CmsRules::localized($row, 'lead', $language),
            cover: isset($covers[(int) $row['cover_media_id']]) ? MediaView::fromRow($covers[(int) $row['cover_media_id']], $language) : null,
            publishedAt: self::isoDate((string) $row['published_at']),
            categories: array_map(static fn (array $category): CategoryView => self::category($category, $language), $categories[(int) $row['id']] ?? []),
        ), $rows);
    }

    /** @param array<string, mixed> $row */
    private static function category(array $row, string $language): CategoryView
    {
        return new CategoryView((string) $row['slug'], CmsRules::localized($row, 'label', $language));
    }

    /**
     * @param array<string, mixed>|null $row
     *
     * @return array{slug: string, title: string}|null
     */
    private static function reference(?array $row, string $language): ?array
    {
        return $row === null ? null : ['slug' => (string) $row['slug'], 'title' => CmsRules::localized($row, 'title', $language)];
    }

    /** Date et heure SQL (heure de Paris) → ISO 8601 avec fuseau. */
    public static function isoDate(string $sqlDate): string
    {
        return (new \DateTimeImmutable($sqlDate, new \DateTimeZone('Europe/Paris')))->format(\DateTimeInterface::ATOM);
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:s');
    }
}
