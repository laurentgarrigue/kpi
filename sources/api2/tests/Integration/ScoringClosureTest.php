<?php

namespace App\Tests\Integration;

/**
 * Closing a match from the scoring console (PAGE_SCORING.md §7.6): end time, officials'
 * comments, then Statut → END, which consolidates the live state into kp_match.
 */
final class ScoringClosureTest extends ApiTestCase
{
    private const ADMIN = 'TADMIN';
    private const MATCH = 9404;

    protected function setUp(): void
    {
        parent::setUp();
        $connection = $this->beginWrites();
        $connection->executeStatement(
            "UPDATE kp_match SET Statut = 'ON', Heure_fin = NULL, Commentaires_officiels = NULL, Validation = '' WHERE Id = ?",
            [self::MATCH]
        );
        $connection->executeStatement('DELETE FROM scoring_live_state WHERE id_match = ?', [self::MATCH]);
    }

    private function param(string $param, string $value, int $expectedStatus = 200): array
    {
        return $this->requestAs(self::ADMIN, 'PUT', '/admin/scoring/gameParam/' . self::MATCH, [
            'param' => $param,
            'value' => $value,
        ], $expectedStatus);
    }

    private function kpMatch(): array
    {
        return $this->beginWrites()->fetchAssociative(
            'SELECT Statut, Heure_fin, Commentaires_officiels FROM kp_match WHERE Id = ?',
            [self::MATCH]
        );
    }

    public function testOfficialsCommentsAreWrittenToTheMatch(): void
    {
        $this->param('Commentaires_officiels', 'Capitaine B : réserve sur le but n°4');

        self::assertSame('Capitaine B : réserve sur le but n°4', $this->kpMatch()['Commentaires_officiels']);
    }

    public function testOfficialsCommentsCanBeCleared(): void
    {
        $this->param('Commentaires_officiels', 'brouillon');
        $this->param('Commentaires_officiels', '');

        self::assertSame('', $this->kpMatch()['Commentaires_officiels']);
    }

    public function testClosingConsolidatesStatusAndEndTime(): void
    {
        $this->param('Heure_fin', '16:42');
        $this->param('Statut', 'END');

        $match = $this->kpMatch();
        self::assertSame('END', $match['Statut']);
        self::assertSame('16:42:00', $match['Heure_fin']);
    }

    public function testCommentsOfALockedMatchAreRefused(): void
    {
        $this->beginWrites()->executeStatement("UPDATE kp_match SET Validation = 'O' WHERE Id = ?", [self::MATCH]);

        $this->param('Commentaires_officiels', 'trop tard', 400);

        self::assertNull($this->kpMatch()['Commentaires_officiels']);
    }
}
