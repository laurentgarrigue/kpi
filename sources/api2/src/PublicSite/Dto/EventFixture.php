<?php

namespace App\PublicSite\Dto;

/** Rendez-vous « événement » de l'accueil (API_PUBLIC_TRANSVERSE.md § 3.7). */
final class EventFixture implements \JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly ?string $libelle,
        public readonly ?string $place,
        public readonly ?string $logo,
        public readonly ?string $start,
        public readonly ?string $end,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'kind' => 'event',
            'id' => $this->id,
            'libelle' => $this->libelle,
            'place' => $this->place,
            'logo' => $this->logo,
            'start' => $this->start,
            'end' => $this->end,
        ];
    }
}
