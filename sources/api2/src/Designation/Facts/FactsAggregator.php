<?php

namespace App\Designation\Facts;

/** Regroupe les faits par club, fait et précision (compétition, rôle, couleur…), en gardant chaque source (REL-04). */
final class FactsAggregator
{
    /**
     * @param iterable<Fact>        $facts
     * @param array<string, string> $clubLabels code => libellé
     *
     * @return list<array{club: string|null, clubLabel: string, fact: string, qualifier: string, quantity: int, details: list<array{label: string, sourceType: string, sourceId: string}>}>
     */
    public static function rows(iterable $facts, array $clubLabels): array
    {
        $rows = [];
        foreach ($facts as $fact) {
            if ($fact->quantity <= 0) {
                continue;
            }
            $key = implode('|', [$fact->club ?? '', $fact->fact, $fact->qualifier]);
            $rows[$key] ??= [
                'club' => $fact->club,
                'clubLabel' => $fact->club === null ? '' : ($clubLabels[$fact->club] ?? $fact->club),
                'fact' => $fact->fact,
                'qualifier' => $fact->qualifier,
                'quantity' => 0,
                'details' => [],
            ];
            $rows[$key]['quantity'] += $fact->quantity;
            $rows[$key]['details'][] = ['label' => $fact->detail, 'sourceType' => $fact->sourceType, 'sourceId' => $fact->sourceId];
        }
        // Clubs à attribuer (null) en dernier, puis par club, fait et précision
        uasort($rows, static fn (array $a, array $b): int => [$a['club'] === null, $a['club'], $a['fact'], $a['qualifier']] <=> [$b['club'] === null, $b['club'], $b['fact'], $b['qualifier']]);

        return array_values($rows);
    }
}
