<?php

namespace App\PublicSite\Dto;

/** Événement publié trouvé par la recherche globale (API_PUBLIC_TRANSVERSE.md § 3.6). */
final class SearchEvent implements \JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $libelle,
        public readonly ?string $place,
        public readonly ?string $start,
        public readonly ?string $end,
    ) {
    }

    /** @return array{id: int, libelle: string, place: ?string, start: ?string, end: ?string} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'libelle' => $this->libelle, 'place' => $this->place, 'start' => $this->start, 'end' => $this->end];
    }
}
