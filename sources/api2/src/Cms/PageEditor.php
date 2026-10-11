<?php

namespace App\Cms;

use Psr\Clock\ClockInterface;

/** Création et modification des pages éditoriales depuis app4 (PAGE_CMS.md § 3, CMS-06). */
final class PageEditor
{
    private const TEXT_FIELDS = ['title' => 255, 'seo_title' => 255, 'seo_description' => 300];

    public function __construct(
        private readonly PageRepository $pages,
        private readonly HtmlSanitizer $sanitizer,
        private readonly ClockInterface $clock,
    ) {
    }

    /** @return list<array<string, mixed>> toutes les pages, en arborescence (parents avant enfants), avec leur chemin */
    public function list(): array
    {
        $rows = $this->pages->all();
        $tree = new PageTree($rows);
        $items = array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'slug' => $row['slug'],
            'title' => $row['title_fr'],
            'status' => $row['status'],
            'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id'],
            'path' => $tree->path((int) $row['id']),
            'depth' => count($tree->lineage((int) $row['id'])) - 1,
            'public' => $tree->isPublic((int) $row['id']),
            'updated_at' => $row['updated_at'],
            'imported' => $row['legacy_wp_id'] !== null,
        ], $rows);
        usort($items, static fn (array $a, array $b): int => strcmp($a['path'], $b['path']));

        return $items;
    }

    /** @return array<string, mixed>|null */
    public function get(int $id): ?array
    {
        $row = $this->pages->find($id);
        if ($row === null) {
            return null;
        }
        $tree = new PageTree($this->pages->all());

        return [
            'id' => (int) $row['id'],
            'slug' => $row['slug'],
            'parent_id' => $row['parent_id'] === null ? null : (int) $row['parent_id'],
            'path' => $tree->path($id),
            'title_fr' => $row['title_fr'],
            'title_en' => $row['title_en'],
            'body_fr' => $row['body_fr'],
            'body_en' => $row['body_en'],
            'status' => $row['status'],
            'seo_title_fr' => $row['seo_title_fr'],
            'seo_title_en' => $row['seo_title_en'],
            'seo_description_fr' => $row['seo_description_fr'],
            'seo_description_en' => $row['seo_description_en'],
            'updated_at' => $row['updated_at'],
            'updated_by' => $row['updated_by'],
            'imported' => $row['legacy_wp_id'] !== null,
        ];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array{id: int, paths: list<string>}
     */
    public function save(?int $id, array $input, string $userCode): array
    {
        $existing = $id === null ? null : $this->pages->find($id);
        $tree = new PageTree($this->pages->all());
        $oldPath = $existing === null ? null : $tree->path((int) $id);
        $errors = new ValidationErrors();
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
            $values['body_' . $language] = $this->sanitizer->sanitize((string) ($input['body_' . $language] ?? ''));
        }

        $slug = trim((string) ($input['slug'] ?? '')) ?: CmsRules::slugify($values['title_fr']);
        if (!CmsRules::isValidSlug($slug)) {
            $errors->add('slug', 'invalid');
        } elseif (CmsRules::isReservedSlug($slug)) {
            $errors->add('slug', 'reserved'); // CMS-06
        } elseif ($this->pages->slugExists($slug, $id)) {
            $errors->add('slug', 'taken');
        }
        $values['slug'] = $slug;

        $status = (string) ($input['status'] ?? CmsRules::STATUS_DRAFT);
        if (!in_array($status, CmsRules::STATUSES, true)) {
            $errors->add('status', 'invalid');
        }
        $values['status'] = $status;

        $parent = $input['parent_id'] ?? null;
        $values['parent_id'] = null;
        if ($parent !== null && $parent !== '') {
            $parentId = (int) $parent;
            if ($this->pages->find($parentId) === null || ($id !== null && $tree->wouldCreateCycle($id, $parentId))) {
                $errors->add('parent_id', 'invalid');
            } else {
                $values['parent_id'] = $parentId;
            }
        }
        $errors->throwIfAny();

        $now = $this->clock->now()->setTimezone(new \DateTimeZone('Europe/Paris'))->format('Y-m-d H:i:s');
        $values['updated_at'] = $now;
        $values['updated_by'] = $userCode;
        if ($existing === null) {
            $id = $this->pages->insert($values + ['created_at' => $now]);
        } else {
            $this->pages->update((int) $id, $values);
        }
        $newTree = new PageTree($this->pages->all());
        $paths = array_values(array_filter(['/', $oldPath, $newTree->path((int) $id)]));

        return ['id' => (int) $id, 'paths' => $paths];
    }

    /** @return list<string>|null */
    public function delete(int $id): ?array
    {
        if ($this->pages->find($id) === null) {
            return null;
        }
        $path = (new PageTree($this->pages->all()))->path($id);
        $this->pages->delete($id);

        return ['/', $path];
    }
}
