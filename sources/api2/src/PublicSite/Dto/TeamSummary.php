<?php

namespace App\PublicSite\Dto;

/** Équipe (kp_equipe) dans une liste ou un résultat de recherche (API_PUBLIC_TRANSVERSE.md § 3.4). */
final class TeamSummary implements \JsonSerializable
{
    public function __construct(
        public readonly int $number,
        public readonly string $label,
        public readonly ?string $clubCode,
        public readonly ?string $clubLabel,
    ) {
    }

    /** @return array{number: int, label: string, club: array{code: ?string, label: ?string}} */
    public function jsonSerialize(): array
    {
        return ['number' => $this->number, 'label' => $this->label, 'club' => ['code' => $this->clubCode, 'label' => $this->clubLabel]];
    }
}
