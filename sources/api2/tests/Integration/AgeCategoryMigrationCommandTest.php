<?php

namespace App\Tests\Integration;

use App\AgeCategory\CompositionAgeCategories;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Migration command of the age category (CATEGORIE_AGE.md stories 22, 31-34, seam 3).
 * The fixture compositions are first brought in line with the rule, then made stale on season 2997:
 * 9511 born « 1901-01-01 » (SCA 9381 and SCB 9384, locked), 9512 born « 0000-00-00 » (SCA 9381),
 * 9515 born in 2980 (SCB 9384, locked, JUN in 2997) with a surclassement.
 */
final class AgeCategoryMigrationCommandTest extends ApiTestCase
{
    /** Referees pool group « France » (POOL, pseudo season 1000). */
    private const REFEREES_POOL_TEAM = 9391;

    private const SURCLASSEMENT = ['Matric' => 9515, 'Saison' => '2997', 'Cat' => 'SEN', 'Date' => '2996-10-01'];

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $compositions = static::getContainer()->get(CompositionAgeCategories::class);
        $compositions->apply($compositions->planAll());

        $this->connection->executeStatement("UPDATE kp_licence SET Naissance = '1901-01-01' WHERE Matric = 9511");
        $this->withZeroDatesAllowed(fn () => $this->connection->executeStatement("UPDATE kp_licence SET Naissance = '0000-00-00' WHERE Matric = 9512"));
        $this->connection->executeStatement("UPDATE kp_licence SET Naissance = '2980-03-01' WHERE Matric = 9515");
        $this->connection->insert('kp_competition_equipe_joueur', ['Id_equipe' => 9384, 'Matric' => 9511, 'Nom' => 'SCRUT', 'Prenom' => 'Capitaine', 'Sexe' => 'M', 'Categ' => 'V9', 'Numero' => 1, 'Capitaine' => '-']);
        $this->connection->executeStatement("UPDATE kp_competition_equipe_joueur SET Categ = 'U21' WHERE (Id_equipe, Matric) IN ((9381, 9511), (9381, 9512), (9384, 9515))");
        $this->connection->insert('kp_surclassement', self::SURCLASSEMENT);
    }

    public function testStory31DryRunReportsConvertedDatesAndCategoryChangesBySeasonWithoutWriting(): void
    {
        $before = $this->snapshot();

        $output = $this->migrate(['--dry-run' => true]);

        self::assertStringContainsString('Simulation', $output);
        self::assertMatchesRegularExpression('/Dates de naissance conventionnelles .*: 2$/m', $output);
        self::assertMatchesRegularExpression('/Catégories d\'âge à changer : 4$/m', $output);
        self::assertMatchesRegularExpression('/^\s+2997\s+U21 → ∅ : 2$/m', $output);
        self::assertMatchesRegularExpression('/^\s+2997\s+V9 → ∅ : 1$/m', $output);
        self::assertMatchesRegularExpression('/^\s+2997\s+U21 → JUN : 1$/m', $output);
        self::assertSame($before, $this->snapshot());
    }

    public function testStory33MigrationClearsConventionalDatesAndRecomputesEveryCompositionLockedIncluded(): void
    {
        $this->migrate();

        self::assertSame([null, null, '2980-03-01'], $this->connection->fetchFirstColumn('SELECT Naissance FROM kp_licence WHERE Matric IN (9511, 9512, 9515) ORDER BY Matric'));
        self::assertSame(
            [[9381, 9511, null], [9381, 9512, null], [9384, 9511, null], [9384, 9515, 'JUN']],
            array_map('array_values', $this->connection->fetchAllAssociative(
                'SELECT Id_equipe, Matric, Categ FROM kp_competition_equipe_joueur WHERE Matric IN (9511, 9512, 9515) ORDER BY Id_equipe, Matric'
            ))
        );
    }

    public function testStory34SecondRunChangesNothing(): void
    {
        $this->migrate();
        $after = $this->snapshot();

        $output = $this->migrate();

        self::assertMatchesRegularExpression('/Dates de naissance conventionnelles .*: 0$/m', $output);
        self::assertMatchesRegularExpression('/Catégories d\'âge à changer : 0$/m', $output);
        self::assertSame($after, $this->snapshot());
    }

    public function testRefereesPoolCategoryOfAConventionalDateIsClearedLikeInTheCompositions(): void
    {
        $this->connection->insert('kp_competition_equipe_joueur', ['Id_equipe' => self::REFEREES_POOL_TEAM, 'Matric' => 9511, 'Nom' => 'SCRUT', 'Prenom' => 'Capitaine', 'Sexe' => 'M', 'Categ' => 'V9', 'Numero' => 0, 'Capitaine' => 'A']);

        $this->migrate();

        self::assertNull($this->connection->fetchOne('SELECT Categ FROM kp_competition_equipe_joueur WHERE Id_equipe = ? AND Matric = 9511', [self::REFEREES_POOL_TEAM]));
    }

    public function testStory22SurclassementsAreLeftUntouched(): void
    {
        $this->migrate();

        self::assertSame([self::SURCLASSEMENT], $this->connection->fetchAllAssociative('SELECT Matric, Saison, Cat, Date FROM kp_surclassement'));
    }

    /** @param array<string, mixed> $options */
    private function migrate(array $options = []): string
    {
        $tester = new CommandTester((new Application(static::$kernel))->find('app:age-category:migrate'));
        $tester->execute($options);
        $tester->assertCommandIsSuccessful();

        return $tester->getDisplay();
    }

    /** @return array{licences: list<array<string, mixed>>, compositions: list<array<string, mixed>>} */
    private function snapshot(): array
    {
        return [
            'licences' => $this->connection->fetchAllAssociative('SELECT Matric, Naissance FROM kp_licence ORDER BY Matric'),
            'compositions' => $this->connection->fetchAllAssociative('SELECT Id_equipe, Matric, Categ FROM kp_competition_equipe_joueur ORDER BY Id_equipe, Matric'),
        ];
    }

    /** The test database is in strict mode (NO_ZERO_DATE): the legacy zero date is only writable without it. */
    private function withZeroDatesAllowed(callable $write): void
    {
        $mode = $this->connection->fetchOne('SELECT @@SESSION.sql_mode');
        $this->connection->executeStatement("SET SESSION sql_mode = ''");
        try {
            $write();
        } finally {
            $this->connection->executeStatement('SET SESSION sql_mode = ?', [$mode]);
        }
    }
}
