<?php

namespace App\PublicSite\Dto;

/**
 * Rendez-vous « groupe » de l'accueil : journées hors événement d'un groupe de compétitions, aux mêmes dates et
 * lieu (API_PUBLIC_TRANSVERSE.md § 3.7).
 */
final class GroupFixture implements \JsonSerializable
{
    public function __construct(
        public readonly string $season,
        public readonly string $code,
        public readonly string $libelle,
        public readonly ?string $libelleEn,
        public readonly ?string $place,
        public readonly ?string $department,
        public readonly ?string $logo,
        public readonly ?string $start,
        public readonly ?string $end,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'kind' => 'group',
            'season' => $this->season,
            'code' => $this->code,
            'libelle' => $this->libelle,
            'libelle_en' => $this->libelleEn,
            'place' => $this->place,
            'department' => $this->department,
            'logo' => $this->logo,
            'start' => $this->start,
            'end' => $this->end,
        ];
    }
}
