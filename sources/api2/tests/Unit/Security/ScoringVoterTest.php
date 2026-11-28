<?php

namespace App\Tests\Unit\Security;

use App\Security\ScoringVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Accès à la console Scoring : profils 1 à 6 et 9 (PAGE_SCORING.md § 6.3). */
final class ScoringVoterTest extends TestCase
{
    /** @return iterable<string, array{int, bool}> */
    public static function levels(): iterable
    {
        foreach ([1, 2, 3, 4, 5, 6, 9] as $level) {
            yield "profil $level" => [$level, true];
        }
        foreach ([7, 8, 10] as $level) {
            yield "profil $level" => [$level, false];
        }
    }

    #[DataProvider('levels')]
    public function testAccessByProfile(int $level, bool $expected): void
    {
        self::assertSame($expected, ScoringVoter::allows($level));
    }
}
