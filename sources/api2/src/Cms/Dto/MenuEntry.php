<?php

namespace App\Cms\Dto;

/** Entrée du menu « Le kayak-polo » (`GET /menu`) : chemin interne ou URL externe. */
final class MenuEntry implements \JsonSerializable
{
    /** @param list<MenuEntry> $children */
    public function __construct(
        public readonly string $label,
        public readonly string $path,
        public readonly bool $external,
        public readonly array $children,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return ['label' => $this->label, 'path' => $this->path, 'external' => $this->external, 'children' => $this->children];
    }
}
