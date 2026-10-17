<?php

namespace App\Cms;

use App\Cms\Dto\PageView;

/** Pages éditoriales publiques (FEATURE_CMS.md § 5.1, PAGE_CONTENT.md § 2). */
final class PageService
{
    public function __construct(private readonly PageRepository $pages)
    {
    }

    public function tree(): PageTree
    {
        return new PageTree($this->pages->all());
    }

    /** Page publique, ou non publiée avec un jeton de prévisualisation (`$previewAllowed`). */
    public function page(string $slug, string $language, ?callable $previewAllowed = null): ?PageView
    {
        $row = $this->pages->findBySlug($slug);
        if ($row === null) {
            return null;
        }
        $id = (int) $row['id'];
        $tree = $this->tree();
        $preview = !$tree->isPublic($id);
        if ($preview && ($previewAllowed === null || !$previewAllowed($id))) {
            return null;
        }

        $parents = array_map(
            static fn (array $parent): array => ['title' => CmsRules::localized($parent, 'title', $language), 'path' => $tree->path((int) $parent['id'])],
            array_reverse(array_slice($tree->lineage($id), 1)),
        );
        $children = array_map(
            static fn (array $child): array => ['title' => CmsRules::localized($child, 'title', $language), 'path' => $tree->path((int) $child['id'])],
            array_values(array_filter($tree->children($id), static fn (array $child): bool => $tree->isPublic((int) $child['id']))),
        );

        return new PageView(
            slug: (string) $row['slug'],
            path: $tree->path($id),
            title: CmsRules::localized($row, 'title', $language),
            body: CmsRules::localized($row, 'body', $language),
            parents: $parents,
            children: $children,
            seo: [
                'title' => CmsRules::localized($row, 'seo_title', $language),
                'description' => CmsRules::localized($row, 'seo_description', $language),
            ],
            preview: $preview,
        );
    }

    /** @return list<array{title: string, path: string}> pages publiques dont le titre contient le terme */
    public function search(string $pattern, int $limit, string $language): array
    {
        $tree = $this->tree();

        return array_values(array_map(
            static fn (array $page): array => ['title' => CmsRules::localized($page, 'title', $language), 'path' => $tree->path((int) $page['id'])],
            array_filter($this->pages->searchPublic($pattern, $limit), static fn (array $page): bool => $tree->isPublic((int) $page['id'])),
        ));
    }
}
