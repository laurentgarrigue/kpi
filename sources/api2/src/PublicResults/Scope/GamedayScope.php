<?php

namespace App\PublicResults\Scope;

/** Une journée de championnat, vue comme un « événement » par app2 (ids >= 3000). */
final class GamedayScope implements ResultsScope
{
    public function __construct(private readonly int $gamedayId)
    {
    }

    public function join(): string
    {
        return '';
    }

    public function condition(): string
    {
        return 'j.Id = ?';
    }

    public function parameters(): array
    {
        return [$this->gamedayId];
    }

    public function isSingleGameday(): bool
    {
        return true;
    }
}
