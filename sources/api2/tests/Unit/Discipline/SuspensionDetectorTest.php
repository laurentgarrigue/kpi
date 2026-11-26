<?php

namespace App\Tests\Unit\Discipline;

use App\Discipline\Card;
use App\Discipline\CardColor;
use App\Discipline\DetectedSuspension;
use App\Discipline\SuspensionCatalog;
use App\Discipline\SuspensionDetector;
use PHPUnit\Framework\TestCase;

/** Détection des cumuls de cartons (FEATURE_CARD_SUSPENSIONS.md § 2, S3, S6 à S8). */
final class SuspensionDetectorTest extends TestCase
{
    private const PLAYER = 1001;
    private const NATIONAL = 2;
    private const REGIONAL = 3;

    private int $sequence = 0;

    public function testSus03EveryEighthGreenCardSuspendsForTheNextGame(): void
    {
        $cards = [];
        for ($i = 1; $i <= 16; ++$i) {
            $cards[] = $this->card(CardColor::Green, game: $i, day: "d$i");
        }

        $found = $this->detect($cards);

        self::assertSame(['card-8', 'card-16'], array_map(static fn (DetectedSuspension $s): string => $s->card->id, $found));
        self::assertSame('GREEN_SEASON', $found[0]->rule->code);
        self::assertSame(1, $found[0]->matches);
        self::assertSame(8, $found[0]->tally);
        self::assertFalse($found[0]->appealJury);
    }

    public function testSus04TwoYellowsInTheSameGameAreNotEnoughThenEachMoreCardOfTheDayCounts(): void
    {
        $found = $this->detect([
            $this->card(CardColor::Yellow, game: 1, day: 'd1'),
            $this->card(CardColor::Yellow, game: 1, day: 'd1'),
        ]);
        self::assertSame([], $found);

        $this->sequence = 0;
        $found = $this->detect([
            $this->card(CardColor::Yellow, game: 1, day: 'd1'),
            $this->card(CardColor::Yellow, game: 1, day: 'd1'),
            $this->card(CardColor::Red, game: 2, day: 'd1'),
            $this->card(CardColor::Yellow, game: 2, day: 'd1'),
        ]);

        self::assertSame(['card-3', 'card-4'], array_map(static fn (DetectedSuspension $s): string => $s->card->id, $found));
        self::assertSame('YELLOW_RED_GAMEDAY', $found[0]->rule->code);
        self::assertSame(2, $found[0]->tally);
        // Le 4e carton jaune ou rouge de la saison déclenche aussi YELLOW_RED_SEASON : une seule suspension (S8)
        self::assertSame(['YELLOW_RED_GAMEDAY', 'YELLOW_RED_SEASON'], $found[1]->ruleCodes());
    }

    public function testSus05YellowCardsOnTwoDifferentDaysDoNotTriggerTheDayRule(): void
    {
        self::assertSame([], $this->detect([
            $this->card(CardColor::Yellow, game: 1, day: 'd1'),
            $this->card(CardColor::Yellow, game: 2, day: 'd2'),
        ]));
    }

    public function testSus06TheFourthYellowOrRedCardOfTheSeasonSuspends(): void
    {
        $found = $this->detect([
            $this->card(CardColor::Yellow, game: 1, day: 'd1'),
            $this->card(CardColor::Red, game: 2, day: 'd2'),
            $this->card(CardColor::Yellow, game: 3, day: 'd3'),
            $this->card(CardColor::Red, game: 4, day: 'd4'),
        ]);

        self::assertCount(1, $found);
        self::assertSame('card-4', $found[0]->card->id);
        self::assertSame(['YELLOW_RED_SEASON'], $found[0]->ruleCodes());
        self::assertSame(4, $found[0]->tally);
    }

    public function testSus07BlackCardsEscalateAndAlwaysGoToTheAppealJury(): void
    {
        $found = $this->detect([
            $this->card(CardColor::Black, game: 1, day: 'd1'),
            $this->card(CardColor::Black, game: 2, day: 'd1'),
            $this->card(CardColor::Black, game: 3, day: 'd2'),
            $this->card(CardColor::Black, game: 4, day: 'd3'),
        ]);

        self::assertSame(['BLACK_GAME', 'BLACK_GAMEDAY', 'BLACK_SEASON', 'BLACK_SEASON'], array_map(static fn (DetectedSuspension $s): string => $s->rule->code, $found));
        // 3 matchs AU TOTAL pour la journée d1 (S15) : le 2e noir n'ajoute qu'un match ; d2 et d3 sont d'autres journées
        self::assertSame([2, 1, 4, 4], array_map(static fn (DetectedSuspension $s): int => $s->matches, $found));
        self::assertSame([2, 3, 4, 4], array_map(static fn (DetectedSuspension $s): ?int => $s->gamedayTotal, $found));
        self::assertSame(['BLACK_GAME', 'BLACK_GAMEDAY'], $found[1]->ruleCodes());
        self::assertSame(['BLACK_GAME', 'BLACK_SEASON'], $found[2]->ruleCodes());
        foreach ($found as $suspension) {
            self::assertTrue($suspension->appealJury);
        }
    }

    public function testSus18BlackCardSuspensionsOfTheSameGamedayDoNotAddUp(): void
    {
        $found = $this->detect([
            $this->card(CardColor::Black, game: 1, day: 'd1'),
            $this->card(CardColor::Black, game: 2, day: 'd1'),
            $this->card(CardColor::Black, game: 3, day: 'd1'),
            $this->card(CardColor::Black, game: 4, day: 'd1'),
        ]);

        // 2, puis 3 au total, puis le 3e noir de la saison porte la journée à 4, le 4e ne la porte pas plus haut
        self::assertSame([2, 3, 4, 4], array_map(static fn (DetectedSuspension $s): ?int => $s->gamedayTotal, $found));
        self::assertSame([2, 1, 1, 0], array_map(static fn (DetectedSuspension $s): int => $s->matches, $found));
        foreach ($found as $suspension) {
            self::assertTrue($suspension->appealJury); // chaque noir reste soumis au jury d'appel
        }
    }

    public function testYellowAndRedSuspensionsOfTheSameGamedayStillAddUp(): void
    {
        $found = $this->detect([
            $this->card(CardColor::Yellow, game: 1, day: 'd1'),
            $this->card(CardColor::Yellow, game: 2, day: 'd1'),
            $this->card(CardColor::Red, game: 2, day: 'd1'),
        ]);

        self::assertSame([1, 1], array_map(static fn (DetectedSuspension $s): int => $s->matches, $found));
        self::assertSame([null, null], array_map(static fn (DetectedSuspension $s): ?int => $s->gamedayTotal, $found));
    }

    public function testSus08OnlyTheNationalSectionIsCountedAndTheOthersDoNotAddToIt(): void
    {
        $cards = [];
        for ($i = 1; $i <= 3; ++$i) {
            $cards[] = $this->card(CardColor::Yellow, game: $i, day: "n$i", section: self::NATIONAL);
        }
        for ($i = 1; $i <= 4; ++$i) {
            $cards[] = $this->card(CardColor::Yellow, game: 10 + $i, day: "r$i", section: self::REGIONAL);
        }

        self::assertSame([], $this->detect($cards));

        $cards[] = $this->card(CardColor::Yellow, game: 20, day: 'n4', section: self::NATIONAL);
        $found = $this->detect($cards);
        self::assertCount(1, $found);
        self::assertSame(self::NATIONAL, $found[0]->card->section);
    }

    public function testEachPlayerHasHisOwnTally(): void
    {
        self::assertSame([], $this->detect([
            $this->card(CardColor::Yellow, game: 1, day: 'd1'),
            $this->card(CardColor::Yellow, game: 2, day: 'd1', matric: 2002),
        ]));
    }

    public function testSus09OneSuspensionPerCardListsEveryRuleItTriggers(): void
    {
        $found = $this->detect([
            $this->card(CardColor::Yellow, game: 1, day: 'd1'),
            $this->card(CardColor::Yellow, game: 2, day: 'd2'),
            $this->card(CardColor::Yellow, game: 3, day: 'd3'),
            $this->card(CardColor::Yellow, game: 4, day: 'd3'),
        ]);

        self::assertCount(1, $found);
        self::assertSame('YELLOW_RED_GAMEDAY', $found[0]->rule->code);
        self::assertSame(['YELLOW_RED_GAMEDAY', 'YELLOW_RED_SEASON'], $found[0]->ruleCodes());
        self::assertSame(1, $found[0]->matches);
    }

    public function testSus10TheGamedayKeyGroupsTheTournamentPhases(): void
    {
        self::assertSame('CP|NCP|2027-05-01|2027-05-02|Lacville', Card::gamedayKey(91, 'NCP', 'CP', '2027-05-01', '2027-05-02', 'Lacville'));
        self::assertSame(Card::gamedayKey(91, 'NCP', 'CP', '2027-05-01', '2027-05-02', 'Lacville'), Card::gamedayKey(92, 'NCP', 'CP', '2027-05-01', '2027-05-02', 'Lacville'));
        self::assertSame('J|91', Card::gamedayKey(91, 'N1H', 'CHPT', '2027-05-01', '2027-05-02', 'Lacville'));
    }

    public function testGreenCardsDoNotCountForTheYellowRules(): void
    {
        self::assertSame([], $this->detect([
            $this->card(CardColor::Green, game: 1, day: 'd1'),
            $this->card(CardColor::Yellow, game: 2, day: 'd1'),
        ]));
    }

    /**
     * @param list<Card> $cards
     *
     * @return list<DetectedSuspension>
     */
    private function detect(array $cards): array
    {
        $catalog = SuspensionCatalog::forSeason('2027');
        self::assertNotNull($catalog);

        return SuspensionDetector::detect($catalog, $cards);
    }

    private function card(CardColor $color, int $game, string $day, int $section = self::NATIONAL, int $matric = self::PLAYER): Card
    {
        return new Card('card-' . ++$this->sequence, $matric, $color, $game, $day, $section);
    }
}
