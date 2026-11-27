<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * API des Désignations (FEATURE_DESIGNATIONS.md § 8.3, ticket designations/03). Fixtures « désignations » de
 * data.sql : saison 2998, NDES (journées 9801, 9802, événement 9801), REGDES (journée 9803), équipe 9801,
 * licenciés 9811 (C001, délégué CNA de l'événement 9801, compte « 9811 » de profil 5), 9812 (C002), 9813.
 * Saison 2998 : aussi le RC 9822 de TSTRN (fixtures du renommage de code).
 */
final class AdminDesignationsTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testTheRoleCatalogueIsServedToApp4(): void
    {
        $roles = $this->requestAs('TADMIN', 'GET', '/admin/designations/roles');

        $delegate = $roles['DELEGUE_CNA'];
        self::assertSame(['EVENEMENT', 'COMPETITION', 'JOURNEE'], $delegate['scopes']);
        self::assertSame(5, $delegate['mandateProfile']);
        self::assertSame(['national' => 'required', 'other' => 'optional'], $delegate['requirement']);
        self::assertSame('Représentant CNA', $delegate['label']);
        self::assertSame(['national' => ['fr' => 'Représentant CNA', 'en' => 'Chief Official'], 'other' => ['fr' => 'Officiel principal', 'en' => 'Chief Official']], $delegate['labels']);
        self::assertCount(12, $roles);
    }

    public function testDes04TheClubIsFrozenFromTheLicenceAtCreation(): void
    {
        $created = $this->create('TADMIN', ['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9812], 201);

        self::assertSame('C002', $created['club']);
        $this->connection->executeStatement("UPDATE kp_licence SET Numero_club = 'C009' WHERE Matric = 9812");
        $listed = $this->requestAs('TADMIN', 'GET', '/admin/designations?season=2998&role=R1');
        self::assertSame(['C002'], self::column($listed['items'], 'club'));
    }

    public function testDes04OnlyProfilesOneAndTwoCorrectTheClub(): void
    {
        $this->requestAs('9811', 'PATCH', '/admin/designations/9801', ['club' => 'C003'], 403);
        $updated = $this->requestAs('TADMIN', 'PATCH', '/admin/designations/9801', ['club' => 'C003']);

        self::assertSame('C003', $updated['club']);
    }

    public function testDes05TheAuthorityIsCnaByDefault(): void
    {
        $created = $this->create('TADMIN', ['role' => 'JUGE_ARBITRE', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'matric' => 9812], 201);

        self::assertSame('CNA', $created['authority']);
    }

    public function testDes01ARoleOnAScopeTheCatalogueRefusesIs422(): void
    {
        $error = $this->create('TADMIN', ['role' => 'DELEGUE_CNA', 'scopeType' => 'EQUIPE', 'scopeId' => '9801', 'matric' => 9812], 422);

        self::assertSame('scope_not_allowed', $error['error']);
    }

    public function testDes02AnUnknownLicenceIsRefused(): void
    {
        $error = $this->create('TADMIN', ['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 1], 422);

        self::assertSame('licence_unknown', $error['error']);
    }

    public function testAnUnknownScopeIsRefused(): void
    {
        $error = $this->create('TADMIN', ['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '123456', 'matric' => 9812], 422);
        self::assertSame('scope_unknown', $error['error']);

        $error = $this->create('TADMIN', ['role' => 'RC', 'scopeType' => 'COMPETITION', 'scopeId' => 'NDES', 'matric' => 9812, 'season' => '2997'], 422);
        self::assertSame('scope_unknown', $error['error']);
    }

    public function testDes03TheSameDesignationTwiceIs409(): void
    {
        $body = ['role' => 'JURY_APPEL', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9812];
        $this->create('TADMIN', $body, 201);

        $error = $this->create('TADMIN', $body, 409);

        self::assertSame('duplicate', $error['error']);
    }

    public function testASingleHolderRoleAlreadyHeldIs422(): void
    {
        $error = $this->create('TADMIN', ['role' => 'DELEGUE_CNA', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'matric' => 9812], 422);

        self::assertSame('scope_full', $error['error']);
    }

    public function testDes06ProfilesFourAndAboveCannotDesignateAndProfileThreeCan(): void
    {
        $this->create('9811', ['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9812], 403);
        // limité à SCA/SCB en 2997, TDIV ne désigne pas sur la journée 9801 de NDES (périmètre du mandat)
        $this->create('TDIV', ['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9812], 403);
        $this->connection->executeStatement("UPDATE kp_user SET Filtre_competition = '', Filtre_saison = '' WHERE Code = 'TDIV'");
        $this->create('TDIV', ['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9812], 201);
        // les responsables de compétition se consultent seulement
        $this->create('TDIV', ['role' => 'RC', 'scopeType' => 'COMPETITION', 'scopeId' => 'NDES', 'matric' => 9813], 403);
        // le bureau de la CNA donne un mandat de profil 2 : un profil 3 ne peut pas le désigner
        $this->create('TDIV', ['role' => 'BUREAU_CNA', 'scopeType' => 'NIVEAU', 'scopeId' => 'NATIONAL', 'matric' => 9813], 403);
        $this->create('9811', ['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9812], 403);
        // R1 de la journée 9801 déjà désigné par TDIV : un profil 1 désigne sur une autre journée
        $this->create('TSUPER', ['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9802', 'matric' => 9812], 201);
    }

    public function testDes07TheDelegateDesignatesTheJuryOnHisOwnScopeOnly(): void
    {
        // délégué de l'événement 9801 : l'événement et ses journées (héritage), pas la journée régionale 9803
        $this->create('9811', ['role' => 'REP_EQUIPES', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'matric' => 9812], 201);
        $this->create('9811', ['role' => 'JURY_APPEL', 'scopeType' => 'JOURNEE', 'scopeId' => '9802', 'matric' => 9813], 201);
        $this->create('9811', ['role' => 'JURY_APPEL', 'scopeType' => 'JOURNEE', 'scopeId' => '9803', 'matric' => 9813], 403);
    }

    public function testDesignationsAreListedWithTheHolderAndFiltered(): void
    {
        $this->create('TADMIN', ['role' => 'RESP_EQUIPE', 'scopeType' => 'EQUIPE', 'scopeId' => '9801', 'matric' => 9813], 201);

        $all = $this->requestAs('TADMIN', 'GET', '/admin/designations?season=2998');
        $teams = $this->requestAs('TADMIN', 'GET', '/admin/designations?season=2998&scopeType=EQUIPE&scopeId=9801');

        self::assertSame(['DELEGUE_CNA', 'RC', 'RESP_EQUIPE'], self::column($all['items'], 'role'));
        self::assertSame([9813], self::column($teams['items'], 'matric'));
        self::assertSame('DESIGNE', $teams['items'][0]['lastName']);
        self::assertNull($teams['items'][0]['club']);
        $this->requestAs('TADMIN', 'GET', '/admin/designations', expectedStatus: 400);
    }

    public function testADesignationIsDeletedAndJournalised(): void
    {
        $this->requestAs('TADMIN', 'DELETE', '/admin/designations/9801', expectedStatus: 204);

        self::assertFalse($this->connection->fetchOne('SELECT 1 FROM kp_designation WHERE id = 9801'));
        self::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_journal WHERE Actions = 'Suppression Désignation'"));
        $this->requestAs('TADMIN', 'DELETE', '/admin/designations/9801', expectedStatus: 404);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<mixed>
     */
    private function create(string $user, array $body, int $expectedStatus): array
    {
        return $this->requestAs($user, 'POST', '/admin/designations', $body + ['season' => '2998'], $expectedStatus);
    }
}
