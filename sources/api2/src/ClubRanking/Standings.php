<?php

namespace App\ClubRanking;

use App\ClubRanking\Model\Adjustment;
use App\ClubRanking\Model\Line;

/**
 * The ranking (§ 5.9): a club's total is the sum of its lines and adjustments (CNC-31); clubs are sorted by total,
 * tied clubs share their rank and the next rank is skipped (CNC-29). Only clubs with points appear.
 */
final class Standings
{
    /**
     * @param list<Line>               $lines
     * @param list<Adjustment>         $adjustments
     * @param callable(string): string $labelOf
     *
     * @return list<StandingRow>
     */
    public static function build(array $lines, array $adjustments, callable $labelOf): array
    {
        $byClub = [];
        foreach ($lines as $line) {
            $byClub[$line->clubCode][$line->ruleCode] = ($byClub[$line->clubCode][$line->ruleCode] ?? 0) + $line->points;
        }
        foreach ($adjustments as $adjustment) {
            $byClub[$adjustment->clubCode][$adjustment->ruleCode] = ($byClub[$adjustment->clubCode][$adjustment->ruleCode] ?? 0) + $adjustment->points;
        }
        $rows = [];
        foreach ($byClub as $club => $byRule) {
            $total = array_sum($byRule);
            if ($total > 0) {
                ksort($byRule, SORT_NATURAL);
                $rows[] = ['club' => (string) $club, 'label' => $labelOf((string) $club), 'total' => $total, 'byRule' => $byRule];
            }
        }
        usort($rows, static fn (array $a, array $b): int => [$b['total'], $a['label']] <=> [$a['total'], $b['label']]);
        $standings = [];
        $rank = 0;
        $previous = null;
        foreach ($rows as $position => $row) {
            if ($row['total'] !== $previous) {
                $rank = $position + 1;
                $previous = $row['total'];
            }
            $standings[] = new StandingRow($row['club'], $row['label'], $row['total'], $row['byRule'], $rank);
        }

        return $standings;
    }
}
