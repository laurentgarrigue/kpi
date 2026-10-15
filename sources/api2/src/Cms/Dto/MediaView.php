<?php

namespace App\Cms\Dto;

use App\Cms\CmsRules;
use App\Cms\MediaUrls;

/** Média public : URL, texte alternatif localisé, dimensions et variantes WebP (FEATURE_CMS.md § 5.3). */
final class MediaView implements \JsonSerializable
{
    /** @param list<array{width: int, url: string}> $variants */
    public function __construct(
        public readonly string $url,
        public readonly string $mime,
        public readonly string $alt,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly array $variants,
        public readonly string $version,
    ) {
    }

    /** @param array<string, mixed> $row ligne de kp_cms_media */
    public static function fromRow(array $row, string $language): self
    {
        $path = (string) $row['path'];
        $width = $row['width'] === null ? null : (int) $row['width'];
        $isImage = MediaUrls::isImage((string) $row['mime']);
        // Cache-busting: a replaced file keeps its URL (CMS-04), the version changes.
        $version = substr(md5((string) $row['updated_at']), 0, 8);

        return new self(
            url: MediaUrls::url($path) . '?v=' . $version,
            mime: (string) $row['mime'],
            alt: CmsRules::localized($row, 'alt', $language),
            width: $width,
            height: $row['height'] === null ? null : (int) $row['height'],
            variants: $isImage ? array_map(
                static fn (int $variant): array => ['width' => $variant, 'url' => MediaUrls::url(MediaUrls::variantPath($path, $variant)) . '?v=' . $version],
                MediaUrls::variantWidths($width),
            ) : [],
            version: $version,
        );
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'url' => $this->url,
            'mime' => $this->mime,
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
            'variants' => $this->variants,
        ];
    }
}
