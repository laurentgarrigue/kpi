<?php

namespace App\Tests\Integration;

use App\AgeCategory\AgeCategoryChange;
use App\AgeCategory\CompositionAgeCategories;
use Doctrine\DBAL\Connection;

/**
 * Cache kp_competition_equipe_joueur.Categ (ADR 0001, CATEGORIE_AGE.md stories 11, 12, 16, 34): recomputed from the
 * birth date and the season of the team, locked competitions included, NULL when the birth date is unknown.
 * Fixtures: 9381 (SCA 2997), 9384 (SCB 2997, locked), 9301 (RCH 2999); player 9511 made born in 2980 here.
 */
final class CompositionAgeCategoriesTest extends ApiTestCase
{
    private Connection $connection;
    private CompositionAgeCategories $compositions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $this->compositions = static::getContainer()->get(CompositionAgeCategories::class);
        $this->connection->executeStatement("UPDATE kp_licence SET Naissance = '2980-03-01' WHERE Matric = 9511");
        $this->compose(9384, 9511, 'V9');
        $this->compose(9301, 9511, 'U21');
    }

    public function testRefreshingAPlayerRewritesAllHisCompositionsWithTheSeasonOfEachTeamLockedIncluded(): void
    {
        $this->compositions->refreshPlayer(9511);

        self::assertSame(['9301' => 'SEN', '9381' => 'JUN', '9384' => 'JUN'], $this->categoriesOf(9511));
    }

    public function testUnknownBirthDateClearsTheCategory(): void
    {
        $this->connection->executeStatement("UPDATE kp_licence SET Naissance = NULL WHERE Matric = 9511");

        $this->compositions->refreshPlayer(9511);

        self::assertSame(['9301' => null, '9381' => null, '9384' => null], $this->categoriesOf(9511));
    }

    public function testRefreshingATeamRewritesOnlyThatTeam(): void
    {
        $this->compositions->refreshTeam(9384);

        self::assertSame(['9301' => 'U21', '9381' => 'U21', '9384' => 'JUN'], $this->categoriesOf(9511));
    }

    public function testPlanningEverythingWritesNothingAndApplyingItIsIdempotent(): void
    {
        $before = $this->connection->fetchAllAssociative('SELECT Id_equipe, Matric, Categ FROM kp_competition_equipe_joueur ORDER BY Id_equipe, Matric');

        $plan = $this->compositions->planAll();

        self::assertSame($before, $this->connection->fetchAllAssociative('SELECT Id_equipe, Matric, Categ FROM kp_competition_equipe_joueur ORDER BY Id_equipe, Matric'));
        self::assertContainsEquals(new AgeCategoryChange(9384, 9511, '2997', 'V9', 'JUN'), $plan->all());
        self::assertSame(1, $plan->countsBySeason()['2999']['U21 → SEN'] ?? null);

        $this->compositions->apply($plan);

        self::assertSame(['9301' => 'SEN', '9381' => 'JUN', '9384' => 'JUN'], $this->categoriesOf(9511));
        self::assertSame(0, $this->compositions->planAll()->count());
    }

    private function compose(int $teamId, int $matric, string $categ): void
    {
        $this->connection->insert('kp_competition_equipe_joueur', ['Id_equipe' => $teamId, 'Matric' => $matric, 'Nom' => 'SCRUT', 'Prenom' => 'Capitaine', 'Sexe' => 'M', 'Categ' => $categ, 'Numero' => 1, 'Capitaine' => '-']);
    }

    /** @return array<string, ?string> Categ by team */
    private function categoriesOf(int $matric): array
    {
        return array_map(
            static fn (mixed $categ): ?string => $categ === null ? null : (string) $categ,
            $this->connection->fetchAllKeyValue('SELECT Id_equipe, Categ FROM kp_competition_equipe_joueur WHERE Matric = ? ORDER BY Id_equipe', [$matric])
        );
    }
}
