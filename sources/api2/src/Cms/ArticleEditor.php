<?php

namespace App\Cms;

use Psr\Clock\ClockInterface;

/**
 * Création et modification des articles depuis app4 (FEATURE_CMS.md § 5.2, PAGE_CMS.md § 2) : validation,
 * filtrage du HTML, catégories et liens. Les erreurs sont levées en bloc ({@see ValidationErrors}).
 */
final class ArticleEditor
{
    public const LIST_PAGE_SIZE = 25;
    public const DEFAULT_SIGNATURE = 'La CNA Kayak-Polo';
    private const TEXT_FIELDS = ['title' => 255, 'seo_title' => 255, 'seo_description' => 300];
    private const DATE_FORMATS = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i', 'Y-m-d H:i'];

    public function __construct(
        private readonly ArticleRepository $articles,
        private readonly CategoryRepository $categories,
        private readonly MediaRepository $media,
        private readonly HtmlSanitizer $sanitizer,
        private readonly ContentAlbums $albums,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return array{items: list<array<string, mixed>>, page: int, pages: int, total: int} */
    public function list(?string $status, ?int $categoryId, ?string $search, int $page): array
    {
        $now = $this->now();
        $total = $this->articles->countForAdmin($status, $categoryId, $search, $now);
        $rows = $this->articles->listForAdmin($status, $categoryId, $search, $now, self::LIST_PAGE_SIZE, ($page - 1) * self::LIST_PAGE_SIZE);
        $categories = $this->articles->categoriesOf(array_map(static fn (array $row): int => (int) $row['id'], $rows));

        return [
            'items' => array_map(fn (array $row): array => [
                'id' => (int) $row['id'],
                'slug' => $row['slug'],
                'title' => $row['title_fr'],
                'status' => $this->displayStatus($row, $now),
                'published_at' => $row['published_at'],
                'updated_at' => $row['updated_at'],
                'updated_by' => $row['updated_by'],
                'imported' => $row['legacy_wp_id'] !== null,
                'categories' => array_map(static fn (array $category): int => (int) $category['id'], $categories[(int) $row['id']] ?? []),
            ], $rows),
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::LIST_PAGE_SIZE)),
            'total' => $total,
        ];
    }

    /** @return array<string, mixed>|null article complet pour l'écran d'édition */
    public function get(int $id): ?array
    {
        $row = $this->articles->find($id);
        if ($row === null) {
            return null;
        }
        $cover = $row['cover_media_id'] === null ? null : $this->media->find((int) $row['cover_media_id']);

        return [
            'id' => (int) $row['id'],
            'slug' => $row['slug'],
            'title_fr' => $row['title_fr'],
            'title_en' => $row['title_en'],
            'lead_fr' => $row['lead_fr'],
            'lead_en' => $row['lead_en'],
            'body_fr' => $row['body_fr'],
            'body_en' => $row['body_en'],
            'cover_media_id' => $row['cover_media_id'] === null ? null : (int) $row['cover_media_id'],
            'cover' => $cover === null ? null : MediaLibrary::summary($cover),
            'status' => $row['status'],
            'display_status' => $this->displayStatus($row, $this->now()),
            'published_at' => $row['published_at'],
            'signature' => $row['signature'],
            'seo_title_fr' => $row['seo_title_fr'],
            'seo_title_en' => $row['seo_title_en'],
            'seo_description_fr' => $row['seo_description_fr'],
            'seo_description_en' => $row['seo_description_en'],
            'categories' => array_map(static fn (array $category): int => (int) $category['id'], $this->articles->categoriesOf([$id])[$id] ?? []),
            'links' => $this->articles->linksOf($id),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'updated_by' => $row['updated_by'],
            'imported' => $row['legacy_wp_id'] !== null,
        ];
    }

    /**
     * Enregistre un article (création si `$id` est null) ; renvoie son id et les chemins publics à purger.
     *
     * @param array<string, mixed> $input
     *
     * @return array{id: int, paths: list<string>}
     */
    public function save(?int $id, array $input, string $userCode): array
    {
        $existing = $id === null ? null : $this->articles->find($id);
        $errors = new ValidationErrors();
        $values = $this->validate($input, $id, $errors);
        $categories = $this->validCategories($input['categories'] ?? [], $errors);
        $links = $this->validLinks($input['links'] ?? [], $errors);
        if ($values['status'] === CmsRules::STATUS_PUBLISHED && $categories === []) {
            $errors->add('categories', 'required'); // ADM-CMS-02
        }
        $errors->throwIfAny();

        $now = $this->now();
        $values['updated_at'] = $now;
        $values['updated_by'] = $userCode;
        if ($values['status'] === CmsRules::STATUS_PUBLISHED && $values['published_at'] === null) {
            $values['published_at'] = $now;
        }
        if ($existing === null) {
            $id = $this->articles->insert($values + ['created_at' => $now, 'author_code' => $userCode]);
        } else {
            $this->articles->update((int) $id, $values);
        }
        $this->articles->replaceCategories((int) $id, $categories);
        $this->articles->replaceLinks((int) $id, $links);
        $this->albums->collect(ContentAlbums::ARTICLES, [$values['body_fr'], $values['body_en']], $values['cover_media_id']);

        $paths = ['/', '/news', '/news/' . $values['slug']];
        if ($existing !== null && $existing['slug'] !== $values['slug']) {
            $paths[] = '/news/' . $existing['slug'];
        }

        return ['id' => (int) $id, 'paths' => $paths];
    }

    /** @return list<string>|null chemins à purger, null si l'article n'existe pas */
    public function delete(int $id): ?array
    {
        $existing = $this->articles->find($id);
        if ($existing === null) {
            return null;
        }
        $this->articles->delete($id);

        return ['/', '/news', '/news/' . $existing['slug']];
    }

    /** Statut affiché : `scheduled` pour un article publié à une date future. */
    private function displayStatus(array $row, string $now): string
    {
        return $row['status'] === CmsRules::STATUS_PUBLISHED && $row['published_at'] !== null && $row['published_at'] > $now ? 'scheduled' : (string) $row['status'];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private function validate(array $input, ?int $id, ValidationErrors $errors): array
    {
        $values = [];
        foreach (self::TEXT_FIELDS as $field => $max) {
            foreach (CmsRules::LANGUAGES as $language) {
                $key = $field . '_' . $language;
                $values[$key] = $errors->maxLength($key, (string) ($input[$key] ?? ''), $max);
            }
        }
        if ($values['title_fr'] === '') {
            $errors->add('title_fr', 'required');
        }
        foreach (CmsRules::LANGUAGES as $language) {
            $values['lead_' . $language] = trim((string) ($input['lead_' . $language] ?? ''));
            $values['body_' . $language] = $this->sanitizer->sanitize((string) ($input['body_' . $language] ?? ''));
        }
        $values['signature'] = $errors->maxLength('signature', (string) ($input['signature'] ?? self::DEFAULT_SIGNATURE), 150);

        $slug = trim((string) ($input['slug'] ?? '')) ?: CmsRules::slugify($values['title_fr']);
        if (!CmsRules::isValidSlug($slug)) {
            $errors->add('slug', 'invalid');
        } elseif ($this->articles->slugExists($slug, $id)) {
            $errors->add('slug', 'taken');
        }
        $values['slug'] = $slug;

        $status = (string) ($input['status'] ?? CmsRules::STATUS_DRAFT);
        if (!in_array($status, CmsRules::STATUSES, true)) {
            $errors->add('status', 'invalid');
        }
        $values['status'] = $status;
        $values['published_at'] = $this->date($input['published_at'] ?? null, 'published_at', $errors);

        $cover = $input['cover_media_id'] ?? null;
        $values['cover_media_id'] = null;
        if ($cover !== null && $cover !== '') {
            $media = $this->media->find((int) $cover);
            if ($media === null || !MediaUrls::isImage((string) $media['mime'])) {
                $errors->add('cover_media_id', 'invalid');
            } else {
                $values['cover_media_id'] = (int) $cover;
            }
        }

        return $values;
    }

    /** @return list<int> */
    private function validCategories(mixed $input, ValidationErrors $errors): array
    {
        $known = $this->categories->ids();
        $categories = is_array($input) ? array_values(array_unique(array_map('intval', $input))) : [];
        if (array_diff($categories, $known) !== [] || !is_array($input)) {
            $errors->add('categories', 'invalid');
        }

        return array_values(array_intersect($categories, $known));
    }

    /** @return list<array{kind: string, ref: string}> */
    private function validLinks(mixed $input, ValidationErrors $errors): array
    {
        $links = [];
        foreach (is_array($input) ? $input : [] as $link) {
            $kind = (string) ($link['kind'] ?? '');
            $ref = trim((string) ($link['ref'] ?? ''));
            if (!in_array($kind, ArticleRepository::LINK_KINDS, true) || !ArticleLinks::isValidRef($kind, $ref)) {
                $errors->add('links', 'invalid');
                continue;
            }
            $links[] = ['kind' => $kind, 'ref' => $ref];
        }

        return $links;
    }

    private function date(mixed $value, string $field, ValidationErrors $errors): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        foreach (self::DATE_FORMATS as $format) {
            $date = \DateTimeImmutable::createFromFormat('!' . $format, (string) $value);
            if ($date !== false) {
                return $date->format('Y-m-d H:i:s');
            }
        }
        $errors->add($field, 'invalid');

        return null;
    }

    private function now(): string
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:s');
    }
}
