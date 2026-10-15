<?php

namespace App\Cms\Dto;

/** Carte d'actualité des listes (`GET /news`, accueil, recherche). */
final class NewsSummary implements \JsonSerializable
{
    /** @param list<CategoryView> $categories */
    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $lead,
        public readonly ?MediaView $cover,
        public readonly string $publishedAt,
        public readonly array $categories,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'slug' => $this->slug,
            'title' => $this->title,
            'lead' => $this->lead,
            'cover' => $this->cover,
            'published_at' => $this->publishedAt,
            'categories' => $this->categories,
        ];
    }
}
