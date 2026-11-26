<?php

namespace App\Report;

final class ReportSection
{
    /** @param list<ReportField> $fields */
    public function __construct(
        public readonly string $key,
        public readonly string $titleFr,
        public readonly string $titleEn,
        public readonly array $fields,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'title' => ['fr' => $this->titleFr, 'en' => $this->titleEn],
            'fields' => array_map(static fn (ReportField $field): array => $field->toArray(), $this->fields),
        ];
    }
}
