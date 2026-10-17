<?php

namespace App\PublicSite\Dto;

/** Compétition trouvée par la recherche globale (API_PUBLIC_TRANSVERSE.md § 3.6). */
final class SearchCompetition implements \JsonSerializable
{
    public function __construct(
        public readonly string $season,
        public readonly string $code,
        public readonly string $displayTitle,
        public readonly ?string $soustitre2,
        public readonly ?string $group,
    ) {
    }

    /** @return array{season: string, code: string, display_title: string, soustitre2: ?string, group: ?string} */
    public function jsonSerialize(): array
    {
        return [
            'season' => $this->season,
            'code' => $this->code,
            'display_title' => $this->displayTitle,
            'soustitre2' => $this->soustitre2,
            'group' => $this->group,
        ];
    }
}
