<?php

namespace App\Tests\Unit\Designation;

use App\Competition\CompetitionLevel;
use PHPUnit\Framework\TestCase;

/** « National » = code commençant par N ou CF (FEATURE_DESIGNATIONS.md § 9, D26 ; PLAYER_ELIGIBILITY_RULES.md). */
final class CompetitionLevelTest extends TestCase
{
    public function testNationalCodesStartWithNOrCf(): void
    {
        foreach (['N1H', 'N3', 'NEM', 'CFH', 'CF15'] as $code) {
            self::assertTrue(CompetitionLevel::isNational($code), $code);
        }
        foreach (['REG07', 'T-R01', 'C-ECA', 'OPEN', ''] as $code) {
            self::assertFalse(CompetitionLevel::isNational($code), $code);
        }
    }
}
