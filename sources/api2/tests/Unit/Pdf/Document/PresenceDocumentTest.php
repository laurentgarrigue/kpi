<?php

namespace App\Tests\Unit\Pdf\Document;

use App\Pdf\Document\PresenceDocument;
use PHPUnit\Framework\TestCase;

/** Columns of the presence sheet (PDF_GOTENBERG.md § 7.2). */
final class PresenceDocumentTest extends TestCase
{
    public function testPaddleAndCertificateAreOnlyCheckedForNationalAndRegionalCompetitions(): void
    {
        self::assertTrue(PresenceDocument::licenceChecks(2), 'national');
        self::assertTrue(PresenceDocument::licenceChecks(3), 'regional');
        foreach ([1, 4, 5, 100, null] as $section) {
            self::assertFalse(PresenceDocument::licenceChecks($section), 'section ' . var_export($section, true));
        }
    }

    public function testRefereesAndInactiveMembersAreListedApart(): void
    {
        $players = array_map(static fn (string $role): array => ['Capitaine' => $role], ['C', '-', 'E', 'A', 'X']);

        $rows = PresenceDocument::teamRows($players, static fn (array $player): array => ['role' => $player['Capitaine']], 'Non joueurs');

        self::assertSame([['role' => 'C'], ['role' => '-'], ['role' => 'E'], ['_group' => 'Non joueurs'], ['role' => 'A'], ['role' => 'X']], $rows);
        self::assertSame([['role' => '-']], PresenceDocument::teamRows([['Capitaine' => '-']], static fn (array $player): array => ['role' => $player['Capitaine']], 'Non joueurs'), 'no separator without non-players');
    }
}
