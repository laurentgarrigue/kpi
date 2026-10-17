<?php

namespace App\PublicResults\Stats;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Registre des statistiques publiques disponibles, dans l'ordre de déclaration des services. */
final class CompetitionStats
{
    /** @var array<string, CompetitionStat> */
    private array $stats = [];

    /** @param iterable<CompetitionStat> $stats */
    public function __construct(#[AutowireIterator(CompetitionStat::TAG)] iterable $stats)
    {
        foreach ($stats as $stat) {
            $this->stats[$stat->kind()] = $stat;
        }
    }

    /** @return list<string> */
    public function kinds(): array
    {
        return array_keys($this->stats);
    }

    public function find(string $kind): ?CompetitionStat
    {
        return $this->stats[$kind] ?? null;
    }
}
