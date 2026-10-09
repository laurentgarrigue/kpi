<?php

namespace App\PublicResults\Scope;

/** Une compétition d'une saison. */
final class CompetitionScope implements ResultsScope
{
    public function __construct(private readonly string $season, private readonly string $code)
    {
    }

    public function join(): string
    {
        return '';
    }

    public function condition(): string
    {
        return 'c.Code = ? AND c.Code_saison = ?';
    }

    public function parameters(): array
    {
        return [$this->code, $this->season];
    }

    public function isSingleGameday(): bool
    {
        return false;
    }

    public function season(): string
    {
        return $this->season;
    }

    public function code(): string
    {
        return $this->code;
    }
}
