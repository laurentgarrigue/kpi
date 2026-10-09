<?php

namespace App\PublicSite\Dto;

/** Classement d'une équipe dans une compétition terminée, pour son palmarès (API_PUBLIC_TRANSVERSE.md § 3.4). */
final class Honour implements \JsonSerializable
{
    public function __construct(
        public readonly string $season,
        public readonly string $code,
        public readonly string $displayTitle,
        public readonly ?string $group,
        public readonly int $rank,
        public readonly ?int $medal,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'season' => $this->season,
            'competition' => ['code' => $this->code, 'display_title' => $this->displayTitle, 'group' => $this->group],
            'rank' => $this->rank,
            'medal' => $this->medal,
        ];
    }
}
