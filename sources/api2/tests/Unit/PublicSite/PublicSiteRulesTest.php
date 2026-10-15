<?php

namespace App\Tests\Unit\PublicSite;

use App\PublicSite\GroupSections;
use App\PublicSite\PublicSiteRules;
use PHPUnit\Framework\TestCase;

final class PublicSiteRulesTest extends TestCase
{
    public function testApi07PositionIsParsedFromCoordOrNull(): void
    {
        self::assertSame(['lat' => 44.84, 'lng' => -0.58], PublicSiteRules::position(' 44.84 , -0.58 '));
        self::assertNull(PublicSiteRules::position(null));
        self::assertNull(PublicSiteRules::position(''));
        self::assertNull(PublicSiteRules::position('pas une position'));
        self::assertNull(PublicSiteRules::position('95, 10'), 'latitude hors bornes');
        self::assertNull(PublicSiteRules::position('0, 0'), 'valeur par défaut, pas une position');
    }

    public function testApi06RolesAreCaptainOrCoach(): void
    {
        self::assertSame('captain', PublicSiteRules::role('C'));
        self::assertSame('coach', PublicSiteRules::role('E'));
        self::assertNull(PublicSiteRules::role('-'));
        self::assertNull(PublicSiteRules::role(null));
    }

    public function testApi08SearchTermIsBetweenTwoAndFiftyCharacters(): void
    {
        self::assertSame('saint malo', PublicSiteRules::searchTerm("  saint \t malo "));
        self::assertSame('éé', PublicSiteRules::searchTerm('éé'), 'caractères, pas octets');
        self::assertNull(PublicSiteRules::searchTerm('a'));
        self::assertNull(PublicSiteRules::searchTerm(null));
        self::assertNull(PublicSiteRules::searchTerm(str_repeat('x', 51)));
    }

    public function testLikePatternsEscapeWildcardsAndAddDashedVariant(): void
    {
        self::assertSame(['%50\\%\\_x\\_%', '%50\\%\\_x\\_%'], PublicSiteRules::containsPatterns('50%_x_'));
        self::assertSame(['%saint malo%', '%saint-malo%'], PublicSiteRules::containsPatterns('saint malo'));
        self::assertSame('C0\\_%', PublicSiteRules::prefixPattern('C0_'));
    }

    public function testApi01GamedaysOfTheSameCompetitionDatesAndPlaceAreMerged(): void
    {
        $row = static fn (int $id, string $code, string $start, string $place): array => [
            'Id' => $id, 'Code_saison' => '2026', 'Code_competition' => $code, 'Date_debut' => $start, 'Date_fin' => $start, 'Lieu' => $place,
        ];

        $merged = PublicSiteRules::mergeGamedays([
            $row(1, 'CF', '2026-05-01', 'Lacville'),
            $row(2, 'CF', '2026-05-01', 'LACVILLE'),
            $row(3, 'CF', '2026-05-02', 'Lacville'),
            $row(4, 'N1H', '2026-05-01', 'Lacville'),
        ]);

        self::assertSame([1, 3, 4], array_column($merged, 'Id'));
    }

    public function testGamedayLabelIsNameDashPlaceAndDepartmentLikeTheLegacyCalendar(): void
    {
        self::assertSame('Coupe de France - Saint-Omer (62)', PublicSiteRules::gamedayLabel('Coupe de France', 'Saint-Omer', '62', 'Fallback'));
        self::assertSame('Worlds - Duisburg (GER)', PublicSiteRules::gamedayLabel('Worlds', 'Duisburg', 'GER', 'Fallback'));
        self::assertSame('Worlds - Duisburg', PublicSiteRules::gamedayLabel('Worlds', 'Duisburg', null, 'Fallback'));
        self::assertSame('Worlds (GER)', PublicSiteRules::gamedayLabel('Worlds', null, 'GER', 'Fallback'));
        self::assertSame('Worlds', PublicSiteRules::gamedayLabel('Worlds', '', ' ', 'Fallback'));
        self::assertSame('Fallback - Lacville (33)', PublicSiteRules::gamedayLabel(null, 'Lacville', '33', 'Fallback'));
        self::assertSame('Fête & tournoi - Lacville', PublicSiteRules::gamedayLabel('Fête &amp; tournoi', 'Lacville', null, 'Fallback'));
    }

    public function testApi10UpcomingFixturesMixEventsAndGroupsNearestFirstEventsFirstOnTies(): void
    {
        $events = [self::fixture('event', 'e1', '2999-06-10', '2999-06-11'), self::fixture('event', 'e2', '2999-06-20', '2999-06-20')];
        $groups = [self::fixture('group', 'g1', '2999-06-10', '2999-06-10'), self::fixture('group', 'g2', '2999-06-01', '2999-06-02'), self::fixture('group', 'g3', '2999-06-30', '2999-06-30')];

        self::assertSame(['g2', 'e1', 'g1', 'e2'], array_column(PublicSiteRules::mergeFixtures($events, $groups, upcoming: true, limit: 4), 'key'));
    }

    public function testApi10RecentFixturesAreMostRecentlyFinishedFirst(): void
    {
        $events = [self::fixture('event', 'e1', '2999-05-09', '2999-05-10'), self::fixture('event', 'e2', '2999-04-01', '2999-04-01')];
        $groups = [self::fixture('group', 'g1', '2999-05-10', '2999-05-10'), self::fixture('group', 'g2', '2999-05-20', '2999-05-20')];

        self::assertSame(['g2', 'e1', 'g1'], array_column(PublicSiteRules::mergeFixtures($events, $groups, upcoming: false, limit: 3), 'key'));
    }

    public function testApi10LegacyTextIsDecodedAndEmptyIsNull(): void
    {
        self::assertSame("Coupe d'Europe", PublicSiteRules::decodedText('Coupe d&#039;Europe'));
        self::assertNull(PublicSiteRules::decodedText('  '));
        self::assertNull(PublicSiteRules::decodedText(null));
    }

    /** @return array{kind: string, key: string, start: string, end: string} */
    private static function fixture(string $kind, string $key, string $start, string $end): array
    {
        return ['kind' => $kind, 'key' => $key, 'start' => $start, 'end' => $end];
    }

    public function testSectionsAreTheGroupSections(): void
    {
        self::assertSame([1, 2, 3, 4, 5, 100], PublicSiteRules::SECTIONS);
    }

    public function testGroupsAreOrganizedBySection(): void
    {
        $sections = GroupSections::organize([
            ['code' => 'ECA', 'libelle' => 'Europe', 'libelle_en' => null, 'section' => '1'],
            ['code' => 'N1H', 'libelle' => 'N1 H', 'libelle_en' => 'N1 M', 'section' => '2'],
            ['code' => 'N1D', 'libelle' => 'N1 D', 'libelle_en' => null, 'section' => '2'],
        ]);

        self::assertSame([1, 2], array_column($sections, 'section'));
        self::assertSame('Competitions_Nationales', $sections[1]['label']);
        self::assertSame(['N1H', 'N1D'], array_column($sections[1]['groups'], 'code'));
    }
}
