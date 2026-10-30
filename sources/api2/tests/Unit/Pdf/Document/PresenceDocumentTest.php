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
}
