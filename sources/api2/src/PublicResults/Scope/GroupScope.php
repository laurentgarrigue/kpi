<?php

namespace App\PublicResults\Scope;

/** Toutes les compétitions d'un groupe (kp_competition.Code_ref) pour une saison. */
final class GroupScope implements ResultsScope
{
    public function __construct(private readonly string $season, private readonly string $groupCode)
    {
    }

    public function join(): string
    {
        return '';
    }

    public function condition(): string
    {
        return 'c.Code_ref = ? AND c.Code_saison = ?';
    }

    public function parameters(): array
    {
        return [$this->groupCode, $this->season];
    }

    public function isSingleGameday(): bool
    {
        return false;
    }
}
