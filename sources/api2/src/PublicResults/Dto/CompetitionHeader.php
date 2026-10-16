<?php

namespace App\PublicResults\Dto;

/** En-tête public d'une compétition (§ 5.1). */
final class CompetitionHeader implements \JsonSerializable
{
    /** @param array{code: string, libelle: ?string, libelle_en: ?string} $group */
    public function __construct(
        public readonly string $code,
        public readonly string $season,
        public readonly array $group,
        public readonly ?string $libelle,
        public readonly ?string $soustitre,
        public readonly ?string $soustitre2,
        public readonly string $displayTitle,
        public readonly string $type,
        public readonly string $status,
        public readonly ?string $level,
        public readonly ?string $banner,
        public readonly ?string $logo,
        public readonly ?string $web,
        public readonly int $qualified,
        public readonly int $eliminated,
        public readonly bool $hasGames,
        public readonly ?int $round,
        public readonly bool $final,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'season' => $this->season,
            'group' => $this->group,
            'libelle' => $this->libelle,
            'soustitre' => $this->soustitre,
            'soustitre2' => $this->soustitre2,
            'display_title' => $this->displayTitle,
            'type' => $this->type,
            'status' => $this->status,
            'level' => $this->level,
            'banner' => $this->banner,
            'logo' => $this->logo,
            'web' => $this->web,
            'qualified' => $this->qualified,
            'eliminated' => $this->eliminated,
            'has_games' => $this->hasGames,
            'round' => $this->round,
            'final' => $this->final,
        ];
    }

    public function summary(): CompetitionSummary
    {
        return new CompetitionSummary($this->code, $this->season, $this->displayTitle, $this->soustitre2);
    }
}
