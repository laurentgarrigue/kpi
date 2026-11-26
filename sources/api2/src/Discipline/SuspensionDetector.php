<?php

namespace App\Discipline;

/**
 * Cumuls de cartons → suspensions (FEATURE_CARD_SUSPENSIONS.md § 2, S3, S6 à S8). Pur : aucune base, aucune horloge.
 *
 * Les cartons arrivent dans l'ordre chronologique (S7) ; chaque règle tient un décompte par licencié, section et
 * portée (match, journée sportive, saison). Le carton qui atteint un seuil déclenche la règle.
 */
final class SuspensionDetector
{
    /**
     * @param list<Card> $cards cartons d'une saison, dans l'ordre chronologique
     *
     * @return list<DetectedSuspension>
     */
    public static function detect(SuspensionCatalog $catalog, array $cards): array
    {
        /** @var array<string, int> $tallies */
        $tallies = [];
        /** @var array<string, array<int, true>> $games */
        $games = [];
        /** @var array<string, int> $gamedayTotals suspension déjà notifiée par journée, pour les règles « au total » */
        $gamedayTotals = [];
        $found = [];
        foreach ($cards as $card) {
            if (!$catalog->coversSection($card->section)) {
                continue;
            }
            $triggered = [];
            foreach ($catalog->rules as $rule) {
                if (!$rule->counts($card->color)) {
                    continue;
                }
                $key = implode('|', [$rule->code, $card->section, $card->matric, $rule->scope->keyOf($card)]);
                $tallies[$key] = ($tallies[$key] ?? 0) + 1;
                $games[$key][$card->matchId] = true;
                $tally = $rule->distinctGames ? count($games[$key]) : $tallies[$key];
                if ($rule->repeat->fires($tally, $rule->threshold)) {
                    $triggered[] = [$rule, $tally];
                }
            }
            if ($triggered !== []) {
                $found[] = self::suspension($card, $triggered, $gamedayTotals);
            }
        }

        return $found;
    }

    /**
     * @param non-empty-list<array{SuspensionRule, int}> $triggered
     * @param array<string, int>                          $gamedayTotals
     */
    private static function suspension(Card $card, array $triggered, array &$gamedayTotals): DetectedSuspension
    {
        [$retained, $tally] = $triggered[0];
        foreach ($triggered as [$rule, $ruleTally]) {
            if ($rule->matches > $retained->matches) {
                [$retained, $tally] = [$rule, $ruleTally];
            }
        }
        $rules = array_column($triggered, 0);
        $matches = $retained->matches;
        $gamedayTotal = null;
        if ($retained->gamedayTotal) {
            // S15 : sur la journée, le total est la suspension la plus lourde ; ce carton n'ajoute que la différence
            $key = implode('|', [$card->section, $card->matric, $card->gamedayKey]);
            $already = $gamedayTotals[$key] ?? 0;
            $gamedayTotal = max($already, $retained->matches);
            $matches = $gamedayTotal - $already;
            $gamedayTotals[$key] = $gamedayTotal;
        }

        return new DetectedSuspension(
            $card,
            $retained,
            $rules,
            $tally,
            $matches,
            array_filter($rules, static fn (SuspensionRule $rule): bool => $rule->appealJury) !== [],
            $gamedayTotal,
        );
    }
}
