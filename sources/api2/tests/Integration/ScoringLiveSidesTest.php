<?php

namespace App\Tests\Integration;

/**
 * Côtés du terrain (PAGE_SCORING.md §0.18) : la console enregistre le côté de départ de l'équipe A
 * (vérifié au coup d'envoi, D15) et les périodes inversées à la main (D4). Les côtés courants se
 * calculent (ScoringRules::sidesFor) ; le serveur ne stocke que ces deux réglages, les renvoie dans
 * l'état live et journalise chaque action (SIDE-16).
 *
 * Données : SQL/fixtures/ — match 9404 (en cours).
 */
final class ScoringLiveSidesTest extends ApiTestCase
{
    private const ADMIN = 'TADMIN';
    private const MATCH = 9404;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = $this->beginWrites();
        $connection->executeStatement('DELETE FROM scoring_live_state WHERE id_match = ?', [self::MATCH]);
        $connection->executeStatement("UPDATE kp_match SET Validation = '' WHERE Id = ?", [self::MATCH]);
    }

    private function state(): array
    {
        return $this->requestAs(self::ADMIN, 'GET', '/admin/scoring/state/' . self::MATCH);
    }

    public function testSidesAreNotCheckedUntilTheTableRecordsThem(): void
    {
        $this->requestAs(self::ADMIN, 'PUT', '/admin/scoring/gameParam/' . self::MATCH, ['param' => 'Periode', 'value' => 'M1']);

        $state = $this->state();

        self::assertNull($state['sidesStart']);
        self::assertSame([], $state['sidesOverrides']);
    }

    public function testTheStartingSideAndTheSwappedPeriodsAreRecordedAndServed(): void
    {
        $this->requestAs(self::ADMIN, 'PUT', '/admin/scoring/sides/' . self::MATCH, [
            'start' => 'A_RIGHT', 'overrides' => ['M2'], 'period' => 'M2', 'time' => '10:00',
        ]);

        $state = $this->state();

        self::assertSame('A_RIGHT', $state['sidesStart']);
        self::assertSame(['M2'], $state['sidesOverrides']);
    }

    public function testEachSidesActionIsJournalizedWithItsPeriodAndTime(): void
    {
        $this->requestAs(self::ADMIN, 'PUT', '/admin/scoring/sides/' . self::MATCH, [
            'start' => 'A_LEFT', 'overrides' => [], 'period' => 'M1', 'time' => '10:00',
        ]);

        $line = $this->beginWrites()->fetchOne(
            "SELECT Journal FROM kp_journal WHERE Actions = 'Scoring côtés' ORDER BY Id DESC LIMIT 1"
        );

        self::assertIsString($line);
        self::assertStringContainsString('départ A_LEFT', $line);
        self::assertStringContainsString('M1 10:00', $line);
    }

    public function testAnUnknownSideOrPeriodIsRefused(): void
    {
        $this->requestAs(self::ADMIN, 'PUT', '/admin/scoring/sides/' . self::MATCH, ['start' => 'LEFT', 'overrides' => []], 400);
        $this->requestAs(self::ADMIN, 'PUT', '/admin/scoring/sides/' . self::MATCH, ['start' => 'A_LEFT', 'overrides' => ['X9']], 400);
    }

    public function testALockedGameRefusesTheSides(): void
    {
        $this->beginWrites()->executeStatement("UPDATE kp_match SET Validation = 'O' WHERE Id = ?", [self::MATCH]);

        $this->requestAs(self::ADMIN, 'PUT', '/admin/scoring/sides/' . self::MATCH, ['start' => 'A_LEFT', 'overrides' => []], 400);
    }
}
