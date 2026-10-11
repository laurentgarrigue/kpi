<?php

namespace App\Cms\Dto;

/** Album d'une galerie (`GET /albums/{slug}`). */
final class AlbumView implements \JsonSerializable
{
    /** @param list<MediaView> $media */
    public function __construct(public readonly string $slug, public readonly string $title, public readonly array $media)
    {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['slug' => $this->slug, 'title' => $this->title, 'media' => $this->media];
    }
}
