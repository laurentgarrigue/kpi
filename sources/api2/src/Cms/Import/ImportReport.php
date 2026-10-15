<?php

namespace App\Cms\Import;

/** Rapport de la reprise : ce qui a été repris, et ce qui demande une intervention (shortcodes, médias, liens). */
final class ImportReport
{
    /** @var list<string> */
    private array $lines = [];

    /** @var array<string, int> */
    private array $counters = [];

    public function info(string $line): void
    {
        $this->lines[] = $line;
    }

    public function warning(string $line): void
    {
        $this->lines[] = '⚠️ ' . $line;
        $this->count('warnings');
    }

    public function count(string $counter, int $by = 1): void
    {
        $this->counters[$counter] = ($this->counters[$counter] ?? 0) + $by;
    }

    public function counter(string $counter): int
    {
        return $this->counters[$counter] ?? 0;
    }

    /** @return list<string> */
    public function lines(): array
    {
        return $this->lines;
    }

    public function summary(): string
    {
        return implode(', ', array_map(static fn (string $key, int $value): string => "$key: $value", array_keys($this->counters), $this->counters));
    }
}
