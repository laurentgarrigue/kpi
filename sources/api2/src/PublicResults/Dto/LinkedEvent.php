<?php

namespace App\PublicResults\Dto;

/** Événement publié contenant des journées d'une portée, avec la part de ces journées (§ 5.6). */
final class LinkedEvent implements \JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly string $libelle,
        public readonly ?string $place,
        public readonly ?string $start,
        public readonly ?string $end,
        public readonly ?string $logo,
        public readonly float $share,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'libelle' => $this->libelle,
            'place' => $this->place,
            'start' => $this->start,
            'end' => $this->end,
            'logo' => $this->logo,
            'share' => $this->share,
        ];
    }
}
