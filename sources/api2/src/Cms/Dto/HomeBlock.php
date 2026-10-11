<?php

namespace App\Cms\Dto;

/**
 * Bloc d'accueil résolu (`GET /home`, PAGE_CONTENT.md § 4) :
 * - `alert` : `{ level, text }` ;
 * - `featured`, `latest_news` : `{ articles: NewsSummary[] }` ;
 * - `partners` : `{ partners: [{ name, url, logo: MediaView|null }] }`.
 */
final class HomeBlock implements \JsonSerializable
{
    /** @param array<string, mixed> $content */
    public function __construct(public readonly string $kind, public readonly array $content)
    {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['kind' => $this->kind] + $this->content;
    }
}
