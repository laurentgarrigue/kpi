<?php

namespace App\Tests\Integration;

use App\Eligibility\PlayerEligibilityRules as Rules;
use App\Tests\Support\FakeGotenbergClient;
use Doctrine\DBAL\Connection;

/**
 * Date de naissance inconnue (CATEGORIE_AGE.md stories 5, 7, 20, 21): the API gives no age category (null), and a
 * competition with age bounds (Age_min or Age_max set, non-zero) gives a NON-blocking warning, whatever the
 * enforcement of the other rules.
 * Built here: N9U (season 2997, national level = blocking, no overclassing, Age_min 18), team 93721 (C001, same club
 * team as 9381) and licence 93722, compliant with the national rules but with an unknown birth date.
 */
final class UnknownBirthDateTest extends ApiTestCase
{
    private const AGE_BOUNDED_TEAM = 93721;
    private const UNKNOWN_BIRTH_DATE_PLAYER = 93722;
    private const NATIONAL_COMPLIANCE = [
        'Origine' => '2997',
        'Type_licence' => 'Carte 1 an Compétition',
        'Etat_certificat_CK' => 'OUI',
        'Pagaie_ECA' => 'PAGV',
    ];

    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
        $this->connection->insert('kp_competition', [
            'Code' => 'N9U', 'Code_saison' => '2997', 'Code_niveau' => 'NAT', 'Libelle' => 'National U', 'Soustitre2' => 'U21 H',
            'BandeauLink' => '', 'LogoLink' => '', 'SponsorLink' => '', 'Titre_actif' => 'O', 'Bandeau_actif' => 'N',
            'Logo_actif' => 'N', 'Code_ref' => 'TSTSCR', 'Code_typeclt' => 'CHPT', 'Statut' => 'ON', 'Verrou' => 'N',
            'Age_min' => 18, 'Age_max' => null,
        ]);
        $this->connection->insert('kp_competition_equipe', [
            'Id' => self::AGE_BOUNDED_TEAM, 'Code_compet' => 'N9U', 'Code_saison' => '2997', 'Libelle' => 'National Alpha',
            'Code_club' => 'C001', 'Numero' => 101, 'Poule' => '', 'Tirage' => 0,
        ]);
        $this->connection->insert('kp_licence', [
            'Matric' => self::UNKNOWN_BIRTH_DATE_PLAYER, 'Nom' => 'INCONNU', 'Prenom' => 'Date', 'Sexe' => 'M',
            'Numero_club' => 'C001', 'Etat' => 'Actif', 'Naissance' => null,
        ] + self::NATIONAL_COMPLIANCE);
    }

    public function testTheApiGivesNoAgeCategoryForAnUnknownBirthDate(): void
    {
        $this->connection->executeStatement("UPDATE kp_licence SET Naissance = '1901-01-01' WHERE Matric = 9512");

        $presences = $this->requestAs('TADMIN', 'GET', '/admin/athletes/9512/participations?season=2997')['presences'];

        self::assertSame([null], self::column($presences, 'categorie'), 'the stored U21 cache is not an age category');
    }

    public function testThePresenceSheetPdfShowsAQuestionMarkForAnUnknownBirthDate(): void
    {
        $this->connection->executeStatement("UPDATE kp_licence SET Naissance = '2980-03-01' WHERE Matric = 9511");

        $this->client->request('GET', '/admin/pdf/presence?season=2997&competition=SCA&team=9381', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TADMIN')]);
        self::assertResponseIsSuccessful();
        $html = (string) static::getContainer()->get(FakeGotenbergClient::class)->html;

        self::assertMatchesRegularExpression('#<td class="[^"]*">JUN</td>#', $html, '9511: 2997 - 2980 = 17');
        self::assertMatchesRegularExpression('#<td class="[^"]*">\?</td>#', $html, '9512: unknown birth date');
        self::assertDoesNotMatchRegularExpression('#<td class="[^"]*">U21</td>#', $html, 'the stored cache is not printed');
    }

    public function testThePresenceByCategoryPdfGroupsTheUnknownBirthDatesUnderAQuestionMark(): void
    {
        $this->client->request('GET', '/admin/pdf/presence?season=2997&competition=SCA&view=category', server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor('TADMIN')]);
        self::assertResponseIsSuccessful();

        self::assertStringContainsString('? - Date de naissance inconnue</h2>', (string) static::getContainer()->get(FakeGotenbergClient::class)->html);
    }

    public function testAnUnknownBirthDateInACompetitionWithAgeBoundsWarnsWithoutBlockingTheAddEvenInBlockingMode(): void
    {
        $added = $this->addUnknownBirthDatePlayer(201);

        self::assertSame([Rules::WARNING_BIRTH_DATE_UNKNOWN], $added['warnings']);
        self::assertSame(
            ['Categ' => null, 'Capitaine' => '-'],
            $this->connection->fetchAssociative('SELECT Categ, Capitaine FROM kp_competition_equipe_joueur WHERE Id_equipe = ? AND Matric = ?', [self::AGE_BOUNDED_TEAM, self::UNKNOWN_BIRTH_DATE_PLAYER]),
        );
    }

    public function testThePresenceSheetWarnsAboutAnUnknownBirthDateWithoutMakingThePlayerNonCompliant(): void
    {
        $this->addUnknownBirthDatePlayer(201);

        $player = $this->presenceSheetPlayer(self::UNKNOWN_BIRTH_DATE_PLAYER);

        self::assertNull($player['categ']);
        self::assertSame([], $player['eligibilityErrors']);
        self::assertSame([Rules::WARNING_BIRTH_DATE_UNKNOWN], $player['eligibilityWarnings']);
    }

    public function testACopiedPlayerWithAnUnknownBirthDateKeepsHisPlayingStatusInABlockingCompetition(): void
    {
        $this->connection->update('kp_licence', self::NATIONAL_COMPLIANCE + ['Naissance' => null], ['Matric' => 9512]);

        $this->requestAs('TADMIN', 'POST', '/admin/teams/' . self::AGE_BOUNDED_TEAM . '/players/copy', ['sourceCompetition' => 'SCA']);

        self::assertSame('-', $this->connection->fetchOne('SELECT Capitaine FROM kp_competition_equipe_joueur WHERE Id_equipe = ? AND Matric = 9512', [self::AGE_BOUNDED_TEAM]));
        self::assertSame([Rules::WARNING_BIRTH_DATE_UNKNOWN], $this->presenceSheetPlayer(9512)['eligibilityWarnings']);
    }

    public function testNoUnknownBirthDateWarningInACompetitionWithoutAgeBounds(): void
    {
        $this->connection->executeStatement("UPDATE kp_competition SET Age_min = 0, Age_max = NULL WHERE Code = 'N9U'");

        self::assertSame([], $this->addUnknownBirthDatePlayer(201)['warnings']);
        self::assertSame([], $this->presenceSheetPlayer(self::UNKNOWN_BIRTH_DATE_PLAYER)['eligibilityWarnings']);
    }

    private function addUnknownBirthDatePlayer(int $expectedStatus): array
    {
        return $this->requestAs(
            'TADMIN',
            'POST',
            '/admin/teams/' . self::AGE_BOUNDED_TEAM . '/players/add',
            ['mode' => 'existing', 'matric' => self::UNKNOWN_BIRTH_DATE_PLAYER, 'capitaine' => '-'],
            $expectedStatus,
        );
    }

    private function presenceSheetPlayer(int $matric): array
    {
        $players = $this->requestAs('TADMIN', 'GET', '/admin/teams/' . self::AGE_BOUNDED_TEAM . '/players')['players'];
        $byMatric = array_combine(self::column($players, 'matric'), $players);

        return $byMatric[$matric];
    }
}
