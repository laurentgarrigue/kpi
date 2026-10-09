<?php

namespace App\PublicSite\Dto;

/** Journée publiée dans le calendrier (API_PUBLIC_TRANSVERSE.md § 3.1). */
final class CalendarEntry implements \JsonSerializable
{
    /**
     * @param array{season: string, code: string, display_title: string, type: string, level: ?string, group: ?string} $competition
     * @param array{id: int, libelle: ?string}|null $event
     */
    public function __construct(
        public readonly int $id,
        public readonly array $competition,
        public readonly ?string $name,
        public readonly ?string $place,
        public readonly ?string $department,
        public readonly ?string $start,
        public readonly ?string $end,
        public readonly ?array $event,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'competition' => $this->competition,
            'name' => $this->name,
            'place' => $this->place,
            'department' => $this->department,
            'start' => $this->start,
            'end' => $this->end,
            'event' => $this->event,
        ];
    }
}
