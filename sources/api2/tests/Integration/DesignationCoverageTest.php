<?php

namespace App\Tests\Integration;

/**
 * Suivi des Désignations (FEATURE_DESIGNATIONS.md SUI-01 à SUI-06, ticket designations/07). Fixtures « désignations » :
 * NDES 2998 nationale (journées 9801, 9802 de l'événement 9801 dont 9811, club C001, est délégué ; équipe 9801 du club
 * C001), REGDES régionale (journée 9803) ; 9812 (C002, sans compte), 9813 (sans club ni compte), 9814 (C003, compte
 * avec email) ; RC 9822 (TSTRN, 9511).
 */
final class DesignationCoverageTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->beginWrites();
    }

    public function testSui01ARequiredRoleIsRedAndARoleWithoutMeaningIsNotApplicable(): void
    {
        $matrix = $this->matrix();

        self::assertSame('red', $this->gamedayCell($matrix, 'NDES', 9801, 'R1')['status']);
        self::assertSame('empty', $this->gamedayCell($matrix, 'NDES', 9801, 'ARBITRE_NJ')['status']);
        self::assertSame('not_applicable', $this->gamedayCell($matrix, 'REGDES', 9803, 'DELEGUE_CNA')['status']);
        self::assertSame('empty', $this->gamedayCell($matrix, 'REGDES', 9803, 'R1')['status']);
        self::assertSame('red', $this->competition($matrix, 'NDES')['cells']['RC']['status']);
    }

    public function testSui02ATeamOfANationalCompetitionWithoutManagerIsRed(): void
    {
        $team = $this->competition($this->matrix(), 'NDES')['teams'][0];

        self::assertSame([9801, 'red'], [$team['id'], $team['cells']['RESP_EQUIPE']['status']]);
    }

    public function testSui03AnInheritedHolderSaysWhereItComesFrom(): void
    {
        $this->designate(['role' => 'DELEGUE_CNA', 'scopeType' => 'JOURNEE', 'scopeId' => '9802', 'matric' => 9813]);
        $matrix = $this->matrix();

        self::assertSame(['EVENEMENT', 9811], $this->holder($this->gamedayCell($matrix, 'NDES', 9801, 'DELEGUE_CNA')));
        self::assertSame(['JOURNEE', 9813], $this->holder($this->gamedayCell($matrix, 'NDES', 9802, 'DELEGUE_CNA')));
    }

    public function testSui04AnOfficialOfTheClubOfAnEngagedTeamOrWithoutEmailIsOrange(): void
    {
        $this->designate(['role' => 'JUGE_ARBITRE', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9813]);
        $matrix = $this->matrix();

        $delegate = $this->gamedayCell($matrix, 'NDES', 9801, 'DELEGUE_CNA');
        self::assertSame(['orange', ['same_club']], [$delegate['status'], $delegate['holders'][0]['warnings']]);
        $judge = $this->gamedayCell($matrix, 'NDES', 9801, 'JUGE_ARBITRE');
        self::assertSame(['orange', ['no_email']], [$judge['status'], $judge['holders'][0]['warnings']]);
    }

    public function testSui05OnlyGapsKeepsTheLinesWithARedCell(): void
    {
        self::assertSame(['NDES'], array_column($this->matrix('&level=national&onlyGaps=1')['competitions'], 'code'));

        $this->designate(['role' => 'RC', 'scopeType' => 'COMPETITION', 'scopeId' => 'NDES', 'matric' => 9812]);
        $this->designate(['role' => 'R1', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'matric' => 9814]); // compte avec email, club C003
        $this->designate(['role' => 'JUGE_ARBITRE', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'matric' => 9813]);
        $this->designate(['role' => 'RESP_EQUIPE', 'scopeType' => 'EQUIPE', 'scopeId' => '9801', 'matric' => 9813]);

        self::assertSame([], $this->matrix('&level=national&onlyGaps=1')['competitions']);
        self::assertSame('green', $this->gamedayCell($this->matrix(), 'NDES', 9801, 'R1')['status']);
        self::assertSame('orange', $this->gamedayCell($this->matrix(), 'NDES', 9801, 'JUGE_ARBITRE')['status']); // sans email
    }

    public function testSui06TheSummaryCountsDesignationsAndCoveredGamedaysByPersonAndClub(): void
    {
        $summary = $this->requestAs('TADMIN', 'GET', '/admin/designations/summary?season=2998');

        $delegate = $this->person($summary, 9811);
        self::assertSame(['DELEGUE_CNA' => 1], $delegate['designations']);
        self::assertSame(['DELEGUE_CNA' => 2], $delegate['gamedays']);
        $c001 = array_values(array_filter($summary['clubs'], static fn (array $c): bool => $c['club'] === 'C001'))[0];
        self::assertSame(2, $c001['people']); // délégué 9811 + RC 9511 de TSTRN

        $later = $this->requestAs('TADMIN', 'GET', '/admin/designations/summary?season=2998&from=2998-04-01');
        self::assertSame([], $this->person($later, 9811)['gamedays']);
    }

    public function testTheFollowUpIsReservedToProfilesOneAndTwo(): void
    {
        $this->requestAs('TDIV', 'GET', '/admin/designations/coverage?season=2998', expectedStatus: 403);
        $this->requestAs('TDIV', 'GET', '/admin/designations/summary?season=2998', expectedStatus: 403);
        $this->requestAs('TADMIN', 'GET', '/admin/designations/coverage?season=2998&level=bad', expectedStatus: 400);
    }

    /** @return array<mixed> */
    private function matrix(string $query = ''): array
    {
        return $this->requestAs('TADMIN', 'GET', '/admin/designations/coverage?season=2998&competitions=NDES,REGDES' . $query);
    }

    /**
     * @param array<mixed> $matrix
     *
     * @return array<mixed>
     */
    private function competition(array $matrix, string $code): array
    {
        return array_values(array_filter($matrix['competitions'], static fn (array $c): bool => $c['code'] === $code))[0];
    }

    /**
     * @param array<mixed> $matrix
     *
     * @return array<mixed>
     */
    private function gamedayCell(array $matrix, string $competition, int $gamedayId, string $role): array
    {
        $gamedays = $this->competition($matrix, $competition)['gamedays'];

        return array_values(array_filter($gamedays, static fn (array $g): bool => $g['id'] === $gamedayId))[0]['cells'][$role];
    }

    /**
     * @param array<mixed> $cell
     *
     * @return array{0: string, 1: int}
     */
    private function holder(array $cell): array
    {
        return [$cell['holders'][0]['origin'], $cell['holders'][0]['matric']];
    }

    /**
     * @param array<mixed> $summary
     *
     * @return array<mixed>
     */
    private function person(array $summary, int $matric): array
    {
        return array_values(array_filter($summary['people'], static fn (array $p): bool => $p['matric'] === $matric))[0];
    }

    /** @param array<string, mixed> $body */
    private function designate(array $body): void
    {
        $this->requestAs('TADMIN', 'POST', '/admin/designations', $body + ['season' => '2998'], 201);
    }
}
