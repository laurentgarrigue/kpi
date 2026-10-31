<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Athlete sheet birth date (CATEGORIE_AGE.md stories 11-16): correcting or clearing it recomputes the age category of
 * every composition of the player, all seasons and locked competitions included; an implausible date is refused.
 * Fixtures: active season 2999; team 9301 (RCH 2999), team 9384 (SCB 2997, locked). The international player
 * 2000001 (only Matric > 2000000 is editable) is created here, composed in both teams with stale categories.
 */
final class AthleteBirthDateTest extends ApiTestCase
{
    private const INTERNATIONAL = 2000001;
    private const ENDPOINT = '/admin/athletes/2000001';

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $this->connection->insert('kp_licence', ['Matric' => self::INTERNATIONAL, 'Origine' => '2997', 'Nom' => 'INTER', 'Prenom' => 'NATIONAL', 'Sexe' => 'M', 'Naissance' => '1901-01-01']);
        $this->compose(9301, 'V9');
        $this->compose(9384, 'V9');
    }

    public function testCorrectingTheBirthDateRecomputesEveryCompositionAllSeasonsLockedIncluded(): void
    {
        $this->requestAs('TADMIN', 'PUT', self::ENDPOINT, $this->sheet('2980-03-01'));

        self::assertSame(['9301' => 'SEN', '9384' => 'JUN'], $this->categories(), '2999 - 2980 = 19 ; 2997 - 2980 = 17 (locked SCB)');
    }

    public function testMarkingTheBirthDateUnknownClearsTheCategoryOfEveryComposition(): void
    {
        $this->requestAs('TADMIN', 'PUT', self::ENDPOINT, $this->sheet(null));

        self::assertNull($this->connection->fetchOne('SELECT Naissance FROM kp_licence WHERE Matric = ?', [self::INTERNATIONAL]));
        self::assertSame(['9301' => null, '9384' => null], $this->categories());
    }

    /** @return iterable<string, array{string}> */
    public static function implausibleBirthDates(): iterable
    {
        yield 'older than 100 in the active season' => ['2898-06-01'];
        yield 'younger than 5 in the active season' => ['2995-06-01'];
        yield 'conventional 1901' => ['1901-01-01'];
        yield 'not a date' => ['2980-02-31'];
    }

    #[DataProvider('implausibleBirthDates')]
    public function testAnImplausibleBirthDateIsRefusedWithAnExplicitErrorAndNothingChanges(string $birthDate): void
    {
        $response = $this->requestAs('TADMIN', 'PUT', self::ENDPOINT, ['nom' => 'RENAMED'] + $this->sheet($birthDate), 400);

        self::assertSame('IMPLAUSIBLE_BIRTHDATE', $response['code']);
        self::assertStringContainsString('5', $response['message']);
        self::assertStringContainsString('100', $response['message']);
        self::assertSame(
            ['Nom' => 'INTER', 'Naissance' => '1901-01-01'],
            $this->connection->fetchAssociative('SELECT Nom, Naissance FROM kp_licence WHERE Matric = ?', [self::INTERNATIONAL])
        );
        self::assertSame(['9301' => 'V9', '9384' => 'V9'], $this->categories());
    }

    /** @return array<string, mixed> */
    private function sheet(?string $birthDate): array
    {
        return ['nom' => 'INTER', 'prenom' => 'NATIONAL', 'sexe' => 'M', 'naissance' => $birthDate, 'origine' => '2997'];
    }

    private function compose(int $teamId, string $categ): void
    {
        $this->connection->insert('kp_competition_equipe_joueur', ['Id_equipe' => $teamId, 'Matric' => self::INTERNATIONAL, 'Nom' => 'INTER', 'Prenom' => 'NATIONAL', 'Sexe' => 'M', 'Categ' => $categ, 'Numero' => 1, 'Capitaine' => '-']);
    }

    /** @return array<string, ?string> Categ by team */
    private function categories(): array
    {
        return array_map(
            static fn (mixed $categ): ?string => $categ === null ? null : (string) $categ,
            $this->connection->fetchAllKeyValue('SELECT Id_equipe, Categ FROM kp_competition_equipe_joueur WHERE Matric = ? ORDER BY Id_equipe', [self::INTERNATIONAL])
        );
    }
}
