<?php

namespace App\PublicSite\Dto;

/**
 * Classement d'une équipe dans une compétition terminée, pour son palmarès (API_PUBLIC_TRANSVERSE.md § 3.4).
 * `final_round` : compétition du tour final (`Code_tour` = 10), sinon classement d'un tour intermédiaire.
 */
final class Honour implements \JsonSerializable
{
    public function __construct(
        public readonly string $season,
        public readonly string $code,
        public readonly string $displayTitle,
        public readonly ?string $group,
        public readonly int $rank,
        public readonly ?int $medal,
        public readonly bool $finalRound,
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
            'final_round' => $this->finalRound,
        ];
    }
}
