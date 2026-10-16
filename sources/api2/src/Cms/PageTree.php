<?php

namespace App\Cms;

/**
 * Arborescence des pages (PAGE_CONTENT.md § 2) : chemin complet `/{parent}/{slug}` et visibilité. Une page est
 * publique si elle et tous ses parents sont publiés.
 */
final class PageTree
{
    /** @var array<int, array<string, mixed>> */
    private readonly array $pages;

    /** @param list<array<string, mixed>> $pages toutes les lignes de kp_cms_page */
    public function __construct(array $pages)
    {
        $byId = [];
        foreach ($pages as $page) {
            $byId[(int) $page['id']] = $page;
        }
        $this->pages = $byId;
    }

    /** @return list<array<string, mixed>> la page puis ses ancêtres jusqu'à la racine */
    public function lineage(int $id): array
    {
        $lineage = [];
        $seen = [];
        $current = $this->pages[$id] ?? null;
        while ($current !== null && !isset($seen[(int) $current['id']])) {
            $seen[(int) $current['id']] = true;
            $lineage[] = $current;
            $current = $current['parent_id'] === null ? null : ($this->pages[(int) $current['parent_id']] ?? null);
        }

        return $lineage;
    }

    public function path(int $id): string
    {
        $slugs = array_map(static fn (array $page): string => (string) $page['slug'], array_reverse($this->lineage($id)));

        return '/' . implode('/', $slugs);
    }

    public function isPublic(int $id): bool
    {
        $lineage = $this->lineage($id);

        return $lineage !== [] && array_reduce($lineage, static fn (bool $all, array $page): bool => $all && $page['status'] === CmsRules::STATUS_PUBLISHED, true);
    }

    /** `$parentId` ferait-il de la page un de ses propres descendants ? */
    public function wouldCreateCycle(int $id, int $parentId): bool
    {
        return in_array($id, array_map(static fn (array $page): int => (int) $page['id'], $this->lineage($parentId)), true);
    }

    /** @return list<array<string, mixed>> enfants directs, par titre */
    public function children(int $id): array
    {
        $children = array_values(array_filter($this->pages, static fn (array $page): bool => $page['parent_id'] !== null && (int) $page['parent_id'] === $id));
        usort($children, static fn (array $a, array $b): int => strcmp((string) $a['title_fr'], (string) $b['title_fr']));

        return $children;
    }

    /** @return list<array<string, mixed>> pages publiques */
    public function publicPages(): array
    {
        return array_values(array_filter($this->pages, fn (array $page): bool => $this->isPublic((int) $page['id'])));
    }
}
