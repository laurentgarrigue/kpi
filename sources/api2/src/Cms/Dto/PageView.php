<?php

namespace App\Cms\Dto;

/** Page éditoriale (`GET /pages/{slug}`, PAGE_CONTENT.md § 2). `path` = chemin complet sur le site. */
final class PageView implements \JsonSerializable
{
    /**
     * @param list<array{title: string, path: string}> $parents  du plus haut au parent direct
     * @param list<array{title: string, path: string}> $children sous-pages publiques
     * @param array{title: string, description: string} $seo
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $path,
        public readonly string $title,
        public readonly string $body,
        public readonly array $parents,
        public readonly array $children,
        public readonly array $seo,
        public readonly bool $preview,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'slug' => $this->slug,
            'path' => $this->path,
            'title' => $this->title,
            'body' => $this->body,
            'parents' => $this->parents,
            'children' => $this->children,
            'seo' => $this->seo,
            'preview' => $this->preview,
        ];
    }
}
