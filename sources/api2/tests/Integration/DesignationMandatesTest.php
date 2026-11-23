<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Comptes et mandats générés par les Désignations (FEATURE_DESIGNATIONS.md MAN-01 à MAN-11, ticket designations/06).
 * Fixtures « désignations » : NDES 2998 (journées 9801, 9802 dans l'événement 9801), REGDES (journée 9803),
 * équipe 9801 (NDES, C001) ; licenciés 9812 et 9813 sans compte, 9814 (compte de profil 7), 9815 (compte de profil 2).
 */
final class DesignationMandatesTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testMan01ALicenseeWithoutAccountGetsAnAccountWhenAnEmailIsGiven(): void
    {
        $created = $this->designate(['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9812, 'email' => 'arbitre@example.test']);

        self::assertSame('created', $created['account']);
        self::assertSame(['Niveau' => 8, 'Mail' => 'arbitre@example.test', 'Identite' => 'DESIGNE Arbitre'], $this->account('9812'));
        self::assertTrue((bool) $this->connection->fetchOne("SELECT 1 FROM kp_user_token WHERE user = '9812'"));
        self::assertSame(['libelle' => 'R1 2998', 'niveau' => 6, 'filtre_competition' => '|NDES|', 'filtre_journee' => '9801'], $this->mandate('9812', 'R1'));
    }

    public function testMan02WithoutEmailTheDesignationIsCreatedWithoutAccountNorMandate(): void
    {
        $created = $this->designate(['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9813]);

        self::assertSame('none', $created['account']);
        self::assertNull($this->account('9813'));
    }

    public function testMan03TheBaseProfileIsKeptAndAMandateIsAdded(): void
    {
        $created = $this->designate(['role' => 'DELEGUE_CNA', 'scopeType' => 'JOURNEE', 'scopeId' => '9803', 'matric' => 9814, 'email' => 'autre@example.test']);

        self::assertSame('existing', $created['account']);
        self::assertSame(['Niveau' => 7, 'Mail' => 'sept@example.test', 'Identite' => 'Profil Sept'], $this->account('9814'));
        self::assertSame(5, $this->mandate('9814', 'DELEGUE_CNA')['niveau']);
    }

    public function testMan04OneMandatePerRoleCoversAllTheDesignationsOfTheSeason(): void
    {
        $this->designate(['role' => 'R1', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'matric' => 9814]);
        $this->designate(['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9803', 'matric' => 9814]);

        self::assertSame(1, (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_user_mandat WHERE user_code = '9814'"));
        self::assertSame(
            ['libelle' => 'R1 2998', 'niveau' => 6, 'filtre_competition' => '|NDES|REGDES|', 'filtre_journee' => '9801,9802,9803'],
            $this->mandate('9814', 'R1'),
        );
    }

    public function testMan05ACompetitionDesignationCoversTheWholeCompetition(): void
    {
        $this->designate(['role' => 'RC', 'scopeType' => 'COMPETITION', 'scopeId' => 'NDES', 'matric' => 9814]);

        self::assertSame(
            ['libelle' => 'Responsable de compétition 2998', 'niveau' => 4, 'filtre_competition' => '|NDES|', 'filtre_journee' => ''],
            $this->mandate('9814', 'RC'),
        );
    }

    public function testMan06AGamedayLinkedToOrUnlinkedFromTheEventEntersOrLeavesTheMandate(): void
    {
        $this->designate(['role' => 'R1', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'matric' => 9814]);

        $this->requestAs('TADMIN', 'PUT', '/admin/gamedays/9803/event/9801');
        self::assertSame('9801,9802,9803', $this->mandate('9814', 'R1')['filtre_journee']);

        $this->requestAs('TADMIN', 'DELETE', '/admin/gamedays/9803/event/9801');
        self::assertSame('9801,9802', $this->mandate('9814', 'R1')['filtre_journee']);
    }

    public function testMan07TheMandateGoesWithTheLastDesignationAndTheAccountStays(): void
    {
        $first = $this->designate(['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9814]);
        $second = $this->designate(['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9802', 'matric' => 9814]);

        $this->requestAs('TADMIN', 'DELETE', "/admin/designations/{$first['id']}", expectedStatus: 204);
        self::assertSame('9802', $this->mandate('9814', 'R1')['filtre_journee']);

        $this->requestAs('TADMIN', 'DELETE', "/admin/designations/{$second['id']}", expectedStatus: 204);
        self::assertNull($this->mandate('9814', 'R1'));
        self::assertNotNull($this->account('9814'));
    }

    public function testMan08AManualMandateIsNeverTouched(): void
    {
        $this->connection->insert('kp_user_mandat', [
            'user_code' => '9814', 'libelle' => 'Mandat à la main', 'niveau' => 6, 'filtre_saison' => '|2998|',
            'filtre_competition' => '|REGDES|', 'filtre_journee' => '', 'id_evenement' => '',
        ]);
        $created = $this->designate(['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9814]);

        $this->requestAs('TADMIN', 'DELETE', "/admin/designations/{$created['id']}", expectedStatus: 204);

        self::assertSame(
            ['libelle' => 'Mandat à la main', 'filtre_competition' => '|REGDES|'],
            $this->connection->fetchAssociative("SELECT libelle, filtre_competition FROM kp_user_mandat WHERE user_code = '9814'"),
        );
    }

    public function testMan09TheTeamManagerMandateIsLimitedToTheClubOfTheTeam(): void
    {
        $this->designate(['role' => 'RESP_EQUIPE', 'scopeType' => 'EQUIPE', 'scopeId' => '9801', 'matric' => 9814]);

        $row = $this->connection->fetchAssociative(
            "SELECT niveau, filtre_competition, limitation_equipe_club FROM kp_user_mandat WHERE user_code = '9814' AND designation_role = 'RESP_EQUIPE'"
        );
        self::assertSame([7, '|NDES|', 'C001'], [(int) $row['niveau'], $row['filtre_competition'], $row['limitation_equipe_club']]);
    }

    public function testMan10TheBureauMandateIsOnlyGeneratedForAProfileAboveTwo(): void
    {
        $this->designate(['role' => 'BUREAU_CNA', 'scopeType' => 'NIVEAU', 'scopeId' => 'NATIONAL', 'matric' => 9815]);
        $this->designate(['role' => 'BUREAU_CNA', 'scopeType' => 'NIVEAU', 'scopeId' => 'NATIONAL', 'matric' => 9814]);

        self::assertNull($this->mandate('9815', 'BUREAU_CNA'));
        self::assertSame(2, $this->mandate('9814', 'BUREAU_CNA')['niveau']);
    }

    public function testMan11RolesWithoutMandateNeverCreateAnAccount(): void
    {
        $created = $this->designate(['role' => 'REP_EQUIPES', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9813, 'email' => 'rep@example.test']);

        self::assertSame('none', $created['account']);
        self::assertNull($this->account('9813'));
        self::assertNull($this->mandate('9814', 'REP_EQUIPES'));
    }

    public function testTheAddDialogAsksForAnEmailOnlyWithoutAccount(): void
    {
        self::assertSame(['exists' => true, 'hasEmail' => true], $this->requestAs('TADMIN', 'GET', '/admin/designations/account?matric=9814'));
        self::assertSame(['exists' => false, 'hasEmail' => false], $this->requestAs('TADMIN', 'GET', '/admin/designations/account?matric=9812'));
        $this->requestAs('TCLUB', 'GET', '/admin/designations/account?matric=9814', expectedStatus: 403);
    }

    public function testAnInvalidEmailIsRefused(): void
    {
        $this->requestAs('TADMIN', 'POST', '/admin/designations', [
            'season' => '2998', 'role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9812, 'email' => 'pas-un-email',
        ], 400);
    }

    /**
     * @param array<string, mixed> $body
     *
     * @return array<mixed>
     */
    private function designate(array $body): array
    {
        return $this->requestAs('TADMIN', 'POST', '/admin/designations', $body + ['season' => '2998'], 201);
    }

    /** @return array{Niveau: int, Mail: string, Identite: string}|null */
    private function account(string $code): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT Niveau, Mail, Identite FROM kp_user WHERE Code = ?', [$code]);

        return $row === false ? null : ['Niveau' => (int) $row['Niveau'], 'Mail' => $row['Mail'], 'Identite' => $row['Identite']];
    }

    /** @return array{libelle: string, niveau: int, filtre_competition: string, filtre_journee: string}|null */
    private function mandate(string $code, string $role): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT libelle, niveau, filtre_competition, filtre_journee FROM kp_user_mandat
             WHERE user_code = ? AND designation_role = ? AND designation_saison = '2998' AND filtre_saison = '|2998|'",
            [$code, $role],
        );

        return $row === false ? null : ['libelle' => $row['libelle'], 'niveau' => (int) $row['niveau'], 'filtre_competition' => $row['filtre_competition'], 'filtre_journee' => $row['filtre_journee']];
    }
}
