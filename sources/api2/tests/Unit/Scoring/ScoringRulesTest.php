<?php

namespace App\Tests\Unit\Scoring;

use App\Scoring\ScoringRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * ScoringRules — règles de jeu du scoring live (refonte, lot 1.2).
 *
 * Ce sont les règles qui décident **ce qui est possible pendant un match** : périodes et
 * prolongations, but en or, progression des cartons, pénalités, chronomètre de tir. Une
 * régression ici ne casse pas un écran, elle produit un **match faux** — un carton refusé
 * à tort, une prolongation qui ne s'ouvre pas, une pénalité levée quand elle ne doit pas.
 *
 * 100 % pur : aucune base, aucun kernel, aucune horloge → suite `unit`, bloquante en CI.
 *
 * ⚠️ **Miroir TypeScript.** `sources/app4/utils/scoringRules.ts` réimplémente ces règles
 * pour la console (retour immédiat sans aller-retour serveur). Ce fichier-ci est la
 * **référence** : toute règle modifiée ici doit l'être là-bas, et inversement.
 *
 * Spécifications : DOC/specs/PAGE_SCORING.md §0.6 (prolongations non bornées), §0.9
 * (règlement 2027 : carton noir, shotclock 40 s), §0.10 (correctif pénalités rouge/noir),
 * §7.4 (cartons et pénalités), §7.5 (périodes et but en or), §6.5 (chronomètre de tir).
 */
final class ScoringRulesTest extends TestCase
{
    // ─────────────────────────────────────────────────────────────────────────
    // Périodes et prolongations (§0.6, §7.5)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Les prolongations sont une série NON BORNÉE (`P1`, `P2`, … `P{n}`) : le legacy
     * plafonnait à deux, ce qui viole le règlement (but en or = autant que nécessaire).
     */
    #[DataProvider('overtimeProvider')]
    public function testOvertimeDetection(string $period, bool $isOvertime, ?int $index): void
    {
        self::assertSame($isOvertime, ScoringRules::isOvertime($period));
        self::assertSame($index, ScoringRules::overtimeIndex($period));
    }

    /** @return iterable<string,array{string,bool,?int}> */
    public static function overtimeProvider(): iterable
    {
        yield 'P1 est une prolongation' => ['P1', true, 1];
        yield 'P12 aussi (aucun plafond)' => ['P12', true, 12];
        yield 'M1 n\'en est pas une' => ['M1', false, null];
        yield 'TB n\'en est pas une' => ['TB', false, null];
    }

    /**
     * Enchaînement des périodes selon le type de match. Un match de classement (C) peut
     * se terminer sur une égalité ; un match éliminatoire (E) enchaîne les prolongations
     * tant que le score est à égalité.
     */
    #[DataProvider('nextPeriodProvider')]
    public function testNextPeriod(
        string $type,
        string $period,
        bool $scoreLevel,
        bool $shootoutEnabled,
        ?string $expected
    ): void {
        self::assertSame($expected, ScoringRules::nextPeriod($type, $period, $scoreLevel, $shootoutEnabled));
    }

    /** @return iterable<string,array{string,string,bool,bool,?string}> */
    public static function nextPeriodProvider(): iterable
    {
        yield 'C : M1 → M2' => ['C', 'M1', true, false, 'M2'];
        yield 'C : après M2, égalité admise' => ['C', 'M2', true, false, null];
        yield 'E : M1 → M2' => ['E', 'M1', false, false, 'M2'];
        yield 'E : M2 avec un vainqueur → fin' => ['E', 'M2', false, false, null];
        yield 'E : M2 à égalité → P1' => ['E', 'M2', true, false, 'P1'];
        yield 'E : P1 à égalité → P2' => ['E', 'P1', true, false, 'P2'];
        yield 'E : P9 à égalité → P10 (pas de plafond)' => ['E', 'P9', true, false, 'P10'];
        yield 'E : P1 avec un vainqueur (but en or) → fin' => ['E', 'P1', false, false, null];
        yield 'E : après TB → fin' => ['E', 'TB', true, false, null];
    }

    /** But en or : en éliminatoire, un but marqué en prolongation met fin au match. */
    #[DataProvider('goldenGoalProvider')]
    public function testGoalEndsMatch(string $type, string $period, bool $expected): void
    {
        self::assertSame($expected, ScoringRules::goalEndsMatch($type, $period));
    }

    /** @return iterable<string,array{string,string,bool}> */
    public static function goldenGoalProvider(): iterable
    {
        yield 'E en P3 : but en or' => ['E', 'P3', true];
        yield 'E en M2 : temps réglementaire' => ['E', 'M2', false];
        yield 'C : jamais de but en or' => ['C', 'P1', false];
    }

    /**
     * Durées : toutes les prolongations partagent une seule valeur (5 min dans les
     * règlements ICF **et** FFCK — §0.9), pas une durée par numéro de prolongation.
     */
    public function testPeriodDurations(): void
    {
        self::assertSame(600, ScoringRules::periodDuration('M1', []), 'mi-temps par défaut : 10 min');
        self::assertSame(300, ScoringRules::periodDuration('P4', []), 'prolongation par défaut : 5 min');
        self::assertSame(180, ScoringRules::periodDuration('TB', []));
        self::assertSame(
            240,
            ScoringRules::periodDuration('P4', ['P' => 240]),
            'la durée de prolongation configurée s\'applique à toutes les P{n}'
        );
    }

    /**
     * Pauses inter-périodes (§4.10 du plan) : 3 min avant M2, 3 min avant la première
     * prolongation, 1 min entre deux prolongations. Ailleurs : pas de pause.
     */
    #[DataProvider('breakProvider')]
    public function testBreakDurationBefore(string $nextPeriod, ?int $expected): void
    {
        self::assertSame($expected, ScoringRules::breakDurationBefore($nextPeriod));
    }

    /** @return iterable<string,array{string,?int}> */
    public static function breakProvider(): iterable
    {
        yield 'mi-temps avant M2' => ['M2', 180];
        yield 'avant la première prolongation' => ['P1', 180];
        yield 'entre deux prolongations' => ['P2', 60];
        yield 'entre prolongations, plus loin' => ['P5', 60];
        yield 'aucune pause avant M1' => ['M1', null];
        yield 'aucune pause avant les tirs au but' => ['TB', null];
    }

    public function testBreakDurationCanBeOverridden(): void
    {
        self::assertSame(120, ScoringRules::breakDurationBefore('M2', ['halftime' => 120]));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Cartons (§7.4, règlement 2027)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Progression vert → jaune → rouge : un joueur ne peut pas recevoir un carton
     * **identique ou inférieur** au précédent. En revanche rien n'impose de commencer par
     * un vert (un premier jaune, voire un premier rouge, est légal selon la gravité), et
     * le carton noir d'exclusion définitive est applicable **à tout moment**.
     *
     * @param list<string> $previous
     */
    #[DataProvider('cardProgressionProvider')]
    public function testCardProgression(array $previous, string $newCard, true|string $expected): void
    {
        self::assertSame($expected, ScoringRules::validateCardProgression($previous, $newCard));
    }

    /** @return iterable<string,array{list<string>,string,true|string}> */
    public static function cardProgressionProvider(): iterable
    {
        yield 'premier carton vert' => [[], 'V', true];
        yield 'premier carton jaune (pas besoin de passer par le vert)' => [[], 'J', true];
        yield 'premier carton rouge (gravité)' => [[], 'R', true];
        yield 'deuxième carton identique refusé' => [['J'], 'J', 'card_not_higher'];
        yield 'carton inférieur refusé' => [['J'], 'V', 'card_not_higher'];
        yield 'carton supérieur accepté' => [['V'], 'J', true];
        yield 'vert puis rouge (on peut sauter le jaune)' => [['V'], 'R', true];
        yield 'plus rien après un rouge' => [['J', 'R'], 'D', 'player_already_out'];
        yield 'plus rien après un noir' => [['D'], 'V', 'player_already_out'];
        yield 'noir applicable à tout moment' => [['V', 'J'], 'D', true];
        yield 'code inconnu refusé' => [[], 'X', 'unknown_card'];
    }

    /**
     * §0.10 — correctif réglementaire. Trois comportements distincts, souvent confondus :
     *  - vert/jaune : pénalité de 2 min, levable sur but encaissé, **le joueur revient** ;
     *  - rouge : pénalité de 2 min qui va **toujours à son terme** (même si des buts sont
     *    encaissés), remplacement à l'issue seulement ;
     *  - noir : **aucune pénalité**, aucun remplacement — l'équipe finit à effectif réduit.
     */
    #[DataProvider('cardConsequencesProvider')]
    public function testCardConsequences(
        string $card,
        bool $createsClock,
        bool $liftableOnGoal,
        bool $playerReturns
    ): void {
        self::assertSame($createsClock, ScoringRules::cardCreatesPenaltyClock($card), "horloge de pénalité pour $card");
        self::assertSame($liftableOnGoal, ScoringRules::penaltyLiftableOnGoal($card), "levée sur but pour $card");
        self::assertSame($playerReturns, ScoringRules::playerReturnsAfterPenalty($card), "retour du joueur pour $card");
    }

    /** @return iterable<string,array{string,bool,bool,bool}> */
    public static function cardConsequencesProvider(): iterable
    {
        //                            horloge, levable, joueur revient
        yield 'vert' => ['V', true, true, true];
        yield 'jaune' => ['J', true, true, true];
        yield 'rouge (2 min pleines, remplacement à l\'issue)' => ['R', true, false, false];
        yield 'noir (exclusion sèche, aucun remplacement)' => ['D', false, false, false];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pénalités (§7.4)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Au plus 2 exclusions concurrentes par équipe : ce n'est pas un plafond arbitraire
     * mais une contrainte de jeu (une équipe ne peut pas descendre sous 3 joueurs).
     *
     * @param list<int> $busy
     */
    #[DataProvider('penaltySlotProvider')]
    public function testFreePenaltySlot(array $busy, ?int $expected): void
    {
        self::assertSame($expected, ScoringRules::freePenaltySlot($busy));
    }

    /** @return iterable<string,array{list<int>,?int}> */
    public static function penaltySlotProvider(): iterable
    {
        yield 'aucune exclusion en cours' => [[], 1];
        yield 'emplacement 1 occupé' => [[1], 2];
        yield 'emplacement 2 occupé' => [[2], 1];
        yield 'les deux occupés : pas de troisième' => [[1, 2], null];
    }

    /**
     * Sur but encaissé, c'est la **plus ancienne pénalité levable** qui saute. Le cas
     * piège — et la raison d'être du §0.10 — est celui d'une pénalité rouge plus ancienne
     * qu'un jaune : c'est le **jaune** qui est levé, le rouge court jusqu'au bout.
     *
     * @param list<array{slot:int,startedAt:string,cardCode:string}> $penalties
     */
    #[DataProvider('penaltyLiftProvider')]
    public function testPenaltySlotToLift(array $penalties, ?int $expected): void
    {
        self::assertSame($expected, ScoringRules::penaltySlotToLift($penalties));
    }

    /** @return iterable<string,array{list<array{slot:int,startedAt:string,cardCode:string}>,?int}> */
    public static function penaltyLiftProvider(): iterable
    {
        yield 'la plus ancienne des levables' => [[
            ['slot' => 1, 'startedAt' => '2026-07-27 15:04:10.000', 'cardCode' => 'V'],
            ['slot' => 2, 'startedAt' => '2026-07-27 15:02:00.000', 'cardCode' => 'J'],
        ], 2];

        yield 'une seule pénalité levable' => [[
            ['slot' => 1, 'startedAt' => '2026-07-27 15:04:10.000', 'cardCode' => 'V'],
        ], 1];

        yield 'un rouge seul n\'est jamais levé' => [[
            ['slot' => 1, 'startedAt' => '2026-07-27 15:02:00.000', 'cardCode' => 'R'],
        ], null];

        yield 'rouge plus ancien : c\'est le jaune qui saute' => [[
            ['slot' => 1, 'startedAt' => '2026-07-27 15:02:00.000', 'cardCode' => 'R'],
            ['slot' => 2, 'startedAt' => '2026-07-27 15:04:10.000', 'cardCode' => 'J'],
        ], 2];

        yield 'aucune pénalité' => [[], null];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Chronomètre de tir (§6.5) — trois commandes, trois états
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Le modèle acté : le **départ EST un reset** (60 s ou 40 s), l'**arrêt** ramène à
     * l'état initial (`--`) et n'est donc pas une pause, et la seule suspension est
     * **automatique**, pilotée par l'arrêt du chrono principal.
     */
    #[DataProvider('shotclockProvider')]
    public function testShotclockTransition(string $state, string $command, string $expected): void
    {
        self::assertSame($expected, ScoringRules::shotclockTransition($state, $command));
    }

    /** @return iterable<string,array{string,string,string}> */
    public static function shotclockProvider(): iterable
    {
        yield 'à l\'arrêt + départ 60 s' => [ScoringRules::SHOTCLOCK_IDLE, 'start60', ScoringRules::SHOTCLOCK_RUNNING];
        yield 'à l\'arrêt + départ 40 s' => [ScoringRules::SHOTCLOCK_IDLE, 'start40', ScoringRules::SHOTCLOCK_RUNNING];
        yield 'en décompte + départ = reset, toujours en décompte' => [ScoringRules::SHOTCLOCK_RUNNING, 'start60', ScoringRules::SHOTCLOCK_RUNNING];
        yield 'en décompte + arrêt = retour à l\'état initial' => [ScoringRules::SHOTCLOCK_RUNNING, 'stop', ScoringRules::SHOTCLOCK_IDLE];
        yield 'arrêt du chrono principal : suspension' => [ScoringRules::SHOTCLOCK_RUNNING, 'gameClockStopped', ScoringRules::SHOTCLOCK_SUSPENDED];
        yield 'arrêt du chrono principal : sans effet si déjà à l\'arrêt' => [ScoringRules::SHOTCLOCK_IDLE, 'gameClockStopped', ScoringRules::SHOTCLOCK_IDLE];
        yield 'reprise du chrono principal : reprise' => [ScoringRules::SHOTCLOCK_SUSPENDED, 'gameClockStarted', ScoringRules::SHOTCLOCK_RUNNING];
        yield 'reprise du chrono principal : sans effet si à l\'arrêt' => [ScoringRules::SHOTCLOCK_IDLE, 'gameClockStarted', ScoringRules::SHOTCLOCK_IDLE];
        yield 'suspendu + arrêt' => [ScoringRules::SHOTCLOCK_SUSPENDED, 'stop', ScoringRules::SHOTCLOCK_IDLE];
        yield 'suspendu + départ 40 s = reset et décompte' => [ScoringRules::SHOTCLOCK_SUSPENDED, 'start40', ScoringRules::SHOTCLOCK_RUNNING];
    }

    /**
     * Il n'existe **pas** de commande « pause » : c'était le modèle envisagé le 2026-07-23,
     * remplacé le 2026-07-27. Ce test verrouille la décision — si quelqu'un réintroduit une
     * pause manuelle, il devra passer par ici et donc par la spec.
     */
    public function testThereIsNoPauseCommand(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        ScoringRules::shotclockTransition(ScoringRules::SHOTCLOCK_IDLE, 'pause');
    }
}
