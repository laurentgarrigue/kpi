<?php

namespace App\Cms\Dto;

/**
 * Article complet (`GET /news/{slug}`). Aucune donnée personnelle : la signature est un texte libre (Q-P4-3), le
 * compte auteur n'est jamais exposé (CMS-10).
 */
final class NewsArticle implements \JsonSerializable
{
    /**
     * @param list<CategoryView>                                  $categories
     * @param list<array{kind: string, label: string, path: string}> $links
     * @param array{title: string, description: string}           $seo
     * @param array{slug: string, title: string}|null              $previous article plus ancien
     * @param array{slug: string, title: string}|null              $next     article plus récent
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $lead,
        public readonly string $body,
        public readonly ?MediaView $cover,
        public readonly ?string $publishedAt,
        public readonly string $updatedAt,
        public readonly string $signature,
        public readonly array $categories,
        public readonly array $links,
        public readonly array $seo,
        public readonly ?array $previous,
        public readonly ?array $next,
        public readonly bool $preview,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'lead' => $this->lead,
            'body' => $this->body,
            'cover' => $this->cover,
            'published_at' => $this->publishedAt,
            'updated_at' => $this->updatedAt,
            'signature' => $this->signature,
            'categories' => $this->categories,
            'links' => $this->links,
            'seo' => $this->seo,
            'previous' => $this->previous,
            'next' => $this->next,
            'preview' => $this->preview,
        ];
    }
}
