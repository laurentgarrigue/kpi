<?php

namespace App\PublicResults\Dto;

/** Équipe engagée dans une compétition (kp_competition_equipe). */
final class TeamRef implements \JsonSerializable
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $number,
        public readonly string $label,
        public readonly ?string $logo,
    ) {
    }

    /** @return array{id: int, number: ?int, label: string, logo: ?string} */
    public function jsonSerialize(): array
    {
        return ['id' => $this->id, 'number' => $this->number, 'label' => $this->label, 'logo' => $this->logo];
    }
}
