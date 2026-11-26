<?php

namespace App\Scoring;

use App\RuleCatalog\RuleCatalogDescriber;
use App\RuleCatalog\RuleCatalogDescription;
use App\RuleCatalog\RuleSection;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * The game rules of ScoringRules for the `rules-catalog` PDF (FEATURE_RULES_CATALOG_PDF.md § 4.1). Every table is
 * obtained by calling the rule (sequence of periods, card matrix, shot-clock transitions), never copied.
 */
#[AsTaggedItem(priority: 40)]
final class ScoringRulesDescriber implements RuleCatalogDescriber
{
    public const CODE = 'scoring';

    public const CARDS = ['V' => 'Verte', 'J' => 'Jaune', 'R' => 'Rouge', 'D' => 'Noire (exclusion définitive)'];
    private const MATCH_TYPES = ['C' => 'Classement (C)', 'E' => 'Élimination (E)'];
    /** Overtimes shown before the ellipsis of an unbounded series. */
    private const SHOWN_OVERTIMES = 3;
    private const SEQUENCE_SEPARATOR = ' → ';
    private const STORED_PERIODS = ['M1', 'M2', 'P1', 'P2', 'TB'];
    private const FRENCH_OVERTIME_PREFIX = 'PRL';
    private const PROGRESSION = ['card_not_higher' => 'refusé (pas supérieur)', 'player_already_out' => 'refusé (joueur exclu)'];
    private const SHOTCLOCK_STATES = [
        ScoringRules::SHOTCLOCK_IDLE => 'En attente (« -- »)',
        ScoringRules::SHOTCLOCK_RUNNING => 'En cours',
        ScoringRules::SHOTCLOCK_SUSPENDED => 'Suspendu',
    ];
    private const SHOTCLOCK_COMMANDS = [
        'start60' => 'Départ 60 s', 'start40' => 'Départ 40 s', 'stop' => 'Stop',
        'gameClockStopped' => 'Arrêt du temps de jeu', 'gameClockStarted' => 'Reprise du temps de jeu',
    ];

    public function code(): string
    {
        return self::CODE;
    }

    public function describe(string $season): RuleCatalogDescription
    {
        return new RuleCatalogDescription(
            title: 'Règles de jeu du scoring',
            version: null,
            sources: ['sources/api2/src/Scoring/ScoringRules.php', 'sources/app4/utils/scoringRules.ts (miroir hors ligne)', 'DOC/specs/PAGE_SCORING.md'],
            sections: [
                RuleSection::table('Enchaînement des périodes (score à égalité)', ['type' => 'Type de match', 'sequence' => 'Périodes'], array_map(
                    fn (string $type, string $label): array => ['type' => $label, 'sequence' => $this->sequence($type)],
                    array_keys(self::MATCH_TYPES),
                    self::MATCH_TYPES,
                )),
                RuleSection::pairs('Durées par défaut (la configuration de la compétition peut les remplacer)', [
                    'Mi-temps (M1, M2)' => self::duration(ScoringRules::periodDuration('M1', [])),
                    'Prolongation (P1, P2…)' => self::duration(ScoringRules::periodDuration('P1', [])),
                    'Tirs au but (TB)' => self::duration(ScoringRules::periodDuration('TB', [])),
                    'Pause avant M2' => self::duration((int) ScoringRules::breakDurationBefore('M2')),
                    'Pause avant la 1re prolongation' => self::duration((int) ScoringRules::breakDurationBefore('P1')),
                    'Pause entre deux prolongations' => self::duration((int) ScoringRules::breakDurationBefore('P2')),
                    'But en or en prolongation (élimination)' => RuleSection::yesNo(ScoringRules::goalEndsMatch('E', 'P1')),
                ]),
                RuleSection::table('Codes des périodes', ['stored' => 'Code enregistré', 'fr' => 'Affiché (fr)', 'en' => 'Affiché (en)'], array_map(static fn (string $period): array => [
                    'stored' => $period,
                    'fr' => ScoringRules::periodDisplayCode($period, self::FRENCH_OVERTIME_PREFIX),
                    'en' => ScoringRules::periodDisplayCode($period),
                ], self::STORED_PERIODS)),
                RuleSection::table('Cartons', ['card' => 'Carton', 'clock' => 'Pénalité chronométrée', 'lift' => 'Levée sur but encaissé', 'returns' => 'Retour du joueur'], array_map(static fn (string $card, string $label): array => [
                    'card' => sprintf('%s (%s)', $label, $card),
                    'clock' => RuleSection::yesNo(ScoringRules::cardCreatesPenaltyClock($card)),
                    'lift' => RuleSection::yesNo(ScoringRules::penaltyLiftableOnGoal($card)),
                    'returns' => RuleSection::yesNo(ScoringRules::playerReturnsAfterPenalty($card)),
                ], array_keys(self::CARDS), self::CARDS)),
                RuleSection::table('Progression des cartons d\'un joueur', ['previous' => 'Carton précédent', ...array_combine(array_keys(self::CARDS), array_keys(self::CARDS))], $this->progression()),
                RuleSection::pairs('Pénalités', [
                    'Pénalités simultanées par équipe, au plus' => self::penaltySlots(),
                    'Pénalité levée quand l\'équipe encaisse un but' => 'la plus ancienne parmi celles qui sont levables',
                ]),
                RuleSection::table('Shot-clock : état après chaque commande', ['state' => 'État', ...self::SHOTCLOCK_COMMANDS], $this->shotclock()),
            ],
        );
    }

    /** Periods followed while the score stays level, from M1. */
    private function sequence(string $type): string
    {
        $periods = ['M1'];
        while (($next = ScoringRules::nextPeriod($type, end($periods), true)) !== null) {
            if ((ScoringRules::overtimeIndex($next) ?? 0) > self::SHOWN_OVERTIMES) {
                $periods[] = '…';
                break;
            }
            $periods[] = $next;
        }

        return implode(self::SEQUENCE_SEPARATOR, $periods);
    }

    /** @return list<array<string, string>> one row per previous card (or none), one column per new card */
    private function progression(): array
    {
        $rows = [];
        foreach ([null, ...array_keys(self::CARDS)] as $previous) {
            $row = ['previous' => $previous === null ? 'aucun' : self::CARDS[$previous]];
            foreach (array_keys(self::CARDS) as $card) {
                $verdict = ScoringRules::validateCardProgression($previous === null ? [] : [$previous], $card);
                $row[$card] = $verdict === true ? 'autorisé' : (self::PROGRESSION[$verdict] ?? $verdict);
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private static function penaltySlots(): int
    {
        $busy = [];
        while (($slot = ScoringRules::freePenaltySlot($busy)) !== null) {
            $busy[] = $slot;
        }

        return count($busy);
    }

    /** @return list<array<string, string>> */
    private function shotclock(): array
    {
        $rows = [];
        foreach (self::SHOTCLOCK_STATES as $state => $label) {
            $row = ['state' => $label];
            foreach (array_keys(self::SHOTCLOCK_COMMANDS) as $command) {
                $row[$command] = self::SHOTCLOCK_STATES[ScoringRules::shotclockTransition($state, $command)];
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private static function duration(int $seconds): string
    {
        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        return match (true) {
            $minutes === 0 => sprintf('%d s', $rest),
            $rest === 0 => sprintf('%d min', $minutes),
            default => sprintf('%d min %d s', $minutes, $rest),
        };
    }
}
