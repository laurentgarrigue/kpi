<?php

namespace App\PublicResults\Dto;

/** Compétition dans une liste de navigation (sœurs d'un groupe, compétitions d'un événement). */
final class CompetitionSummary implements \JsonSerializable
{
    public function __construct(
        public readonly string $code,
        public readonly string $season,
        public readonly string $displayTitle,
        public readonly ?string $soustitre2,
    ) {
    }

    /** @return array{code: string, display_title: string, soustitre2: ?string} */
    public function jsonSerialize(): array
    {
        return ['code' => $this->code, 'display_title' => $this->displayTitle, 'soustitre2' => $this->soustitre2];
    }

    /** @return array{code: string, season: string, display_title: string, soustitre2: ?string} */
    public function withSeason(): array
    {
        return [
            'code' => $this->code,
            'season' => $this->season,
            'display_title' => $this->displayTitle,
            'soustitre2' => $this->soustitre2,
        ];
    }
}
