<?php

namespace App\PublicSite\Dto;

/** Club dans la liste des clubs ou un résultat de recherche (API_PUBLIC_TRANSVERSE.md § 3.5). */
final class ClubSummary implements \JsonSerializable
{
    /** @param array{lat: float, lng: float}|null $position */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly ?string $departmentCode,
        public readonly ?string $departmentLabel,
        public readonly ?string $logo,
        public readonly ?array $position,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'label' => $this->label,
            'department' => ['code' => $this->departmentCode, 'label' => $this->departmentLabel],
            'logo' => $this->logo,
            'position' => $this->position,
        ];
    }
}
