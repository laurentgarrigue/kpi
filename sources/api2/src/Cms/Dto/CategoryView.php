<?php

namespace App\Cms\Dto;

/** Catégorie d'actualités (FEATURE_CMS.md § 3.1). */
final class CategoryView implements \JsonSerializable
{
    public function __construct(public readonly string $slug, public readonly string $label)
    {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['slug' => $this->slug, 'label' => $this->label];
    }
}
