<?php

namespace App\PublicSite\Dto;

/**
 * Fiche d'un club (API_PUBLIC_TRANSVERSE.md § 3.5). Coordonnées de la STRUCTURE (site, e-mail, adresse postale),
 * jamais d'une personne (Q-P3-2).
 */
final class ClubSheet implements \JsonSerializable
{
    /**
     * @param array{code: ?string, label: ?string} $region
     * @param list<array{number: int, label: string}> $teams
     */
    public function __construct(
        public readonly ClubSummary $club,
        public readonly array $region,
        public readonly ?string $www,
        public readonly ?string $email,
        public readonly ?string $postal,
        public readonly array $teams,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        $club = $this->club->jsonSerialize();

        return [
            'code' => $club['code'],
            'label' => $club['label'],
            'department' => $club['department'],
            'region' => $this->region,
            'www' => $this->www,
            'email' => $this->email,
            'postal' => $this->postal,
            'position' => $club['position'],
            'logo' => $club['logo'],
            'teams' => $this->teams,
        ];
    }
}
