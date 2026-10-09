<?php

namespace App\PublicSite\Dto;

/** Fiche d'une équipe (API_PUBLIC_TRANSVERSE.md § 3.4). */
final class TeamSheet implements \JsonSerializable
{
    /**
     * @param array{image: string, season: ?string}|null $colors
     * @param array{image: string, season: ?string}|null $photo photo d'équipe, maintenue jusqu'à l'étude RGPD (Q-P3-3)
     * @param list<Honour> $honours
     * @param list<array{season: string, competitions: list<array{code: string, display_title: string}>}> $seasons
     */
    public function __construct(
        public readonly TeamSummary $team,
        public readonly ?string $logo,
        public readonly ?array $colors,
        public readonly ?array $photo,
        public readonly array $honours,
        public readonly array $seasons,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            ...$this->team->jsonSerialize(),
            'logo' => $this->logo,
            'colors' => $this->colors,
            'photo' => $this->photo,
            'honours' => $this->honours,
            'seasons' => $this->seasons,
        ];
    }
}
