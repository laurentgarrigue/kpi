<?php

namespace App\Tests\Integration;

/** MED-25: validating or unvalidating a game in api2 computes or removes its medals at once (§ 6.3). */
final class MatchMedalsValidationTest extends ApiTestCase
{
    public function testMed25UnvalidatingRemovesTheMedalsAndValidatingAgainComputesThem(): void
    {
        $db = $this->beginWrites();
        $count = static fn (): int => (int) $db->fetchOne('SELECT COUNT(*) FROM kp_match_medal WHERE match_id = 9461');

        self::assertSame('N', $this->requestAs('TSUPER', 'PATCH', '/admin/games/9461/validation')['validation']);
        self::assertSame(0, $count());

        self::assertSame('O', $this->requestAs('TSUPER', 'PATCH', '/admin/games/9461/validation')['validation']);
        self::assertGreaterThan(0, $count());
    }

    public function testMed25BulkValidationComputesTheGames(): void
    {
        $db = $this->beginWrites();

        $this->requestAs('TSUPER', 'PATCH', '/admin/games/bulk/validation', ['ids' => [9464]]);

        self::assertSame(1, (int) $db->fetchOne('SELECT is_eligible FROM kp_match_insight WHERE match_id = 9464'));
    }
}
