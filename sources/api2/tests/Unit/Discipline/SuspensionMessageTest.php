<?php

namespace App\Tests\Unit\Discipline;

use App\Discipline\CardColor;
use App\Discipline\SuspensionCatalog;
use App\Discipline\SuspensionMessage;
use App\Discipline\SuspensionRule;
use PHPUnit\Framework\TestCase;

/** Texte des notifications de suspension (FEATURE_CARD_SUSPENSIONS.md § 5). */
final class SuspensionMessageTest extends TestCase
{
    private const CONTEXT = [
        'player' => 'SUSPENDU Joueur', 'club' => 'C001', 'competition' => 'Nationale 1', 'Code_competition' => 'N1H',
        'gameday' => 'Étape 1', 'Lieu' => 'Susville', 'Numero_ordre' => 12, 'Date_match' => '2027-03-07',
        'team_a' => 'Un', 'team_b' => 'Deux',
    ];

    public function testTheSuspensionMailGivesThePlayerTheGameTheRuleAndTheSuspension(): void
    {
        $message = new SuspensionMessage(9961, self::CONTEXT, CardColor::Yellow, $this->rule('YELLOW_RED_SEASON'), 2, 4, 1, false);

        self::assertSame('KPI — Suspension : SUSPENDU Joueur (9961)', $message->subject());
        $body = $message->body();
        self::assertStringContainsString('Joueur : SUSPENDU Joueur, licence 9961, club C001', $body);
        self::assertStringContainsString('Match : n° 12 du 2027-03-07, Un - Deux', $body);
        self::assertStringContainsString('4e carton de la saison, section Nationale', $body);
        self::assertStringContainsString('Suspension : 1 match (le match suivant)', $body);
        self::assertStringNotContainsString("jury d'appel a été ouvert", $body);
    }

    public function testAnAppealJurySuspensionRecallsTheDraftAndTheOtherRules(): void
    {
        $message = new SuspensionMessage(9961, self::CONTEXT, CardColor::Black, $this->rule('BLACK_GAMEDAY'), 2, 2, 3, true,
            [$this->rule('BLACK_GAME')], 'https://app4.test/reports/7');

        $body = $message->body();
        self::assertStringContainsString("Suspension : 3 matchs + jury d'appel", $body);
        self::assertStringContainsString('Autres règles atteintes par ce carton : 1 carton noir dans un match', $body);
        self::assertStringContainsString('https://app4.test/reports/7', $body);
    }

    public function testSus18ASecondBlackCardOfTheDayGivesTheIncreaseAndTheGamedayTotal(): void
    {
        $more = new SuspensionMessage(9961, self::CONTEXT, CardColor::Black, $this->rule('BLACK_GAMEDAY'), 2, 2, 1, true, gamedayTotal: 3);
        self::assertStringContainsString("Suspension : 1 de plus, soit 3 matchs au total pour la journée + jury d'appel", $more->body());

        $none = new SuspensionMessage(9961, self::CONTEXT, CardColor::Black, $this->rule('BLACK_SEASON'), 2, 4, 0, true, gamedayTotal: 4);
        self::assertStringContainsString("Suspension : aucun match de plus, 4 matchs au total pour la journée + jury d'appel", $none->body());

        $first = new SuspensionMessage(9961, self::CONTEXT, CardColor::Black, $this->rule('BLACK_GAME'), 2, 1, 2, true, gamedayTotal: 2);
        self::assertStringContainsString("Suspension : 2 matchs + jury d'appel", $first->body());
    }

    public function testTheCancellationMailNamesTheCancelledSuspension(): void
    {
        $message = new SuspensionMessage(9961, self::CONTEXT, CardColor::Black, $this->rule('BLACK_GAME'), 2, 1, 2, true);

        self::assertSame('KPI — Suspension annulée : SUSPENDU Joueur (9961)', $message->cancellationSubject());
        self::assertStringContainsString("Suspension annulée : 2 matchs + jury d'appel", $message->cancellationBody());
    }

    private function rule(string $code): SuspensionRule
    {
        foreach (SuspensionCatalog::forSeason('2027')?->rules ?? [] as $rule) {
            if ($rule->code === $code) {
                return $rule;
            }
        }
        self::fail("Règle $code absente");
    }
}
