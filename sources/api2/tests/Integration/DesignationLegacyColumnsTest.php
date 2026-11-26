<?php

namespace App\Tests\Integration;

use Doctrine\DBAL\Connection;

/**
 * Recopie des Désignations dans les colonnes legacy de kp_journee (FEATURE_DESIGNATIONS.md DES-11, ticket
 * designations/04). Fixtures « désignations » : NDES 2998, journées 9801 et 9802 dans l'événement 9801 (délégué
 * 9811), journée 9803 (REGDES) ; licenciés 9811 DESIGNE Délégué, 9812 DESIGNE Arbitre, 9813 DESIGNE Sansclub.
 */
final class DesignationLegacyColumnsTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->beginWrites();
    }

    public function testDes11AnEventDesignationIsCopiedToEachOfItsGamedays(): void
    {
        $this->designate(['role' => 'JUGE_ARBITRE', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'matric' => 9812]);

        self::assertSame('DESIGNE Arbitre (9812)', $this->gamedayColumn(9801, 'ChefArbitre'));
        self::assertSame('DESIGNE Arbitre (9812)', $this->gamedayColumn(9802, 'ChefArbitre'));
        self::assertNull($this->gamedayColumn(9803, 'ChefArbitre'));
    }

    public function testDes11AGamedayDesignationOverridesTheInheritedOne(): void
    {
        $this->designate(['role' => 'R1', 'scopeType' => 'EVENEMENT', 'scopeId' => '9801', 'matric' => 9812]);
        $this->designate(['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9802', 'matric' => 9813]);

        self::assertSame('DESIGNE Arbitre (9812)', $this->gamedayColumn(9801, 'Responsable_R1'));
        self::assertSame('DESIGNE Sansclub (9813)', $this->gamedayColumn(9802, 'Responsable_R1'));
    }

    public function testDes11DeletingTheLastDesignationOfARoleEmptiesItsColumn(): void
    {
        $id = $this->designate(['role' => 'R1', 'scopeType' => 'COMPETITION', 'scopeId' => 'NDES', 'matric' => 9812])['id'];

        $this->requestAs('TADMIN', 'DELETE', "/admin/designations/$id", expectedStatus: 204);

        self::assertNull($this->gamedayColumn(9801, 'Responsable_R1'));
    }

    public function testDes11NonPlayingRefereesFillTheirColumnsInDesignationOrder(): void
    {
        $this->designate(['role' => 'ARBITRE_NJ', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9813, 'order' => 2]);
        $first = $this->designate(['role' => 'ARBITRE_NJ', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9812, 'order' => 1]);
        self::assertSame(['DESIGNE Arbitre (9812)', 'DESIGNE Sansclub (9813)'], [$this->gamedayColumn(9801, 'Arb_nj1'), $this->gamedayColumn(9801, 'Arb_nj2')]);

        $this->requestAs('TADMIN', 'PATCH', "/admin/designations/{$first['id']}", ['order' => 3]);

        self::assertSame(['DESIGNE Sansclub (9813)', 'DESIGNE Arbitre (9812)'], [$this->gamedayColumn(9801, 'Arb_nj1'), $this->gamedayColumn(9801, 'Arb_nj2')]);
    }

    public function testAColumnOfARoleNoDesignationTouchesIsLeftAsTyped(): void
    {
        $this->connection->executeStatement("UPDATE kp_journee SET ChefArbitre = 'SAISI Main' WHERE Id = 9801");

        $this->designate(['role' => 'R1', 'scopeType' => 'JOURNEE', 'scopeId' => '9801', 'matric' => 9812]);

        self::assertSame('SAISI Main', $this->gamedayColumn(9801, 'ChefArbitre'));
    }

    public function testDes10ANewPhaseOfTheCompetitionGetsTheInheritedColumns(): void
    {
        $this->designate(['role' => 'R1', 'scopeType' => 'COMPETITION', 'scopeId' => 'NDES', 'matric' => 9812]);

        $created = $this->requestAs('TADMIN', 'POST', '/admin/gamedays', ['codeCompetition' => 'NDES', 'codeSaison' => '2998', 'phase' => 'Finale'], 201);

        self::assertSame('DESIGNE Arbitre (9812)', $this->gamedayColumn((int) $created['id'], 'Responsable_R1'));
    }

    public function testLinkingAndUnlinkingAGamedayToAnEventCopiesThenEmptiesTheInheritedColumns(): void
    {
        $this->connection->executeStatement("UPDATE kp_journee SET Delegue = NULL WHERE Id = 9803");

        $this->requestAs('TADMIN', 'PUT', '/admin/gamedays/9803/event/9801');
        self::assertSame('DESIGNE Délégué (9811)', $this->gamedayColumn(9803, 'Delegue'));

        $this->requestAs('TADMIN', 'DELETE', '/admin/gamedays/9803/event/9801');
        self::assertNull($this->gamedayColumn(9803, 'Delegue'));
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

    private function gamedayColumn(int $gamedayId, string $column): ?string
    {
        $value = $this->connection->fetchOne("SELECT $column FROM kp_journee WHERE Id = ?", [$gamedayId]);

        return $value === false ? null : $value;
    }
}
