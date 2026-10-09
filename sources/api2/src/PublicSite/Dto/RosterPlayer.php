<?php

namespace App\PublicSite\Dto;

/**
 * Membre de la composition d'une équipe, avec ses statistiques de match (API_PUBLIC_TRANSVERSE.md § 3.4).
 * Ni licence, ni sexe, ni date de naissance (stratégie § 11).
 */
final class RosterPlayer implements \JsonSerializable
{
    public function __construct(
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?int $number,
        public readonly ?string $category,
        public readonly ?string $role,
        public readonly int $goals,
        public readonly int $green,
        public readonly int $yellow,
        public readonly int $red,
        public readonly int $redFinal,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'first_name' => $this->firstName,
            'last_name' => $this->lastName,
            'number' => $this->number,
            'category' => $this->category,
            'role' => $this->role,
            'goals' => $this->goals,
            'green' => $this->green,
            'yellow' => $this->yellow,
            'red' => $this->red,
            'red_final' => $this->redFinal,
        ];
    }
}
