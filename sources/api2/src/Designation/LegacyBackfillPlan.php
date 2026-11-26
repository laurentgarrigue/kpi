<?php

namespace App\Designation;

/** Résultat de {@see LegacyBackfillPlanner::forGameday()}. */
final class LegacyBackfillPlan
{
    /**
     * @param list<array{role: string, matric: int, order: int}>  $create     Désignations à créer sur la journée
     * @param list<array{role: string, column: string, text: string}> $unresolved textes sans licence lisible
     */
    public function __construct(public readonly array $create, public readonly array $unresolved)
    {
    }
}
