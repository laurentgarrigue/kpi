<?php

namespace App\PublicSite\Dto;

/** Équipe classée d'une compétition finale, dans l'historique (API_PUBLIC_TRANSVERSE.md § 3.3). */
final class PodiumTeam implements \JsonSerializable
{
    public function __construct(
        public readonly int $rank,
        public readonly ?int $number,
        public readonly string $label,
        public readonly ?string $logo,
        public readonly ?int $medal,
    ) {
    }

    /** @return array{rank: int, team: array{number: ?int, label: string, logo: ?string}, medal: ?int} */
    public function jsonSerialize(): array
    {
        return [
            'rank' => $this->rank,
            'team' => ['number' => $this->number, 'label' => $this->label, 'logo' => $this->logo],
            'medal' => $this->medal,
        ];
    }
}
