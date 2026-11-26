<?php

namespace App\Tests\Unit\Pdf;

use App\Pdf\PdfCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Minimum role of each document (PDF_GOTENBERG.md § 7, DROITS_PAR_PROFIL.md « Documents PDF »), with the decisions of
 * § 7.6 (I1 to I5). PDF-AUTH-08 checks these roles end to end on the migrated documents.
 */
final class PdfCatalogTest extends TestCase
{
    public function testPdfAuth08AdminRolesFollowTheSpecMatrix(): void
    {
        $expected = [
            PdfCatalog::TEAMS_GROUPS => 'ROLE_VIEWER',
            PdfCatalog::PRESENCE => 'ROLE_PRESENCE_VIEWER',
            PdfCatalog::SCRUTINEERING_CONTROL => 'ROLE_SCRUTINEER',      // I1
            PdfCatalog::REFEREES_POOL => 'ROLE_ADMIN',
            PdfCatalog::INSTANCES => 'ROLE_VIEWER',
            PdfCatalog::GAMES_LIST => 'ROLE_SCORER',
            PdfCatalog::SCORESHEET => 'ROLE_SCORER',                     // I4
            PdfCatalog::RANKING => 'ROLE_ORGANIZER',                     // I5: computed ranking, profiles 1–6
            PdfCatalog::RANKING_TEAM_DETAIL => 'ROLE_ORGANIZER',
            PdfCatalog::RANKING_GAMEDAY_DETAIL => 'ROLE_ORGANIZER',
            PdfCatalog::RANKING_PHASES => 'ROLE_ORGANIZER',
            PdfCatalog::RANKING_TIEBREAK => 'ROLE_ORGANIZER',            // I5
            PdfCatalog::STATS => 'ROLE_VIEWER',
            PdfCatalog::CARDS => 'ROLE_ORGANIZER',
            PdfCatalog::SCHEMA => 'ROLE_VIEWER',                         // as the Schema page
            PdfCatalog::CLUB_RANKING => 'ROLE_SUPER_ADMIN',              // PAGE_CLASSEMENT_NATIONAL_CLUBS.md Q-1: profile 1
            PdfCatalog::RULES_CATALOG => 'ROLE_SUPER_ADMIN',             // FEATURE_RULES_CATALOG_PDF.md RCAT-11
        ];

        foreach ($expected as $document => $role) {
            self::assertSame($role, PdfCatalog::adminRole($document), $document);
        }
        self::assertNull(PdfCatalog::adminRole(PdfCatalog::GAMES_GRID), 'public version only');
        self::assertNull(PdfCatalog::adminRole(PdfCatalog::QRCODES), 'public version only');
    }

    public function testPdfAuth09OptionsAddingDataHaveTheirOwnRole(): void
    {
        self::assertSame([], PdfCatalog::optionRoles(PdfCatalog::PRESENCE, ['photos' => '1', 'category' => 'U18']));
        self::assertSame(['ROLE_ORGANIZER'], PdfCatalog::optionRoles(PdfCatalog::PRESENCE, ['visa' => '1']), 'I2');
        self::assertSame(['ROLE_ORGANIZER'], PdfCatalog::optionRoles(PdfCatalog::PRESENCE, ['category' => 'u21']));
        self::assertSame(['ROLE_ADMIN'], PdfCatalog::optionRoles(PdfCatalog::PRESENCE, ['view' => 'birthyear']));
        self::assertSame([], PdfCatalog::optionRoles(PdfCatalog::PRESENCE, ['visa' => '0']));
    }

    public function testScoresheetIsNotPublic(): void
    {
        // § 7.3.2: no public scoresheet, the public uses the game view of app2.
        self::assertFalse(PdfCatalog::isPublicDocument(PdfCatalog::SCORESHEET));
        self::assertFalse(PdfCatalog::isPublicDocument(PdfCatalog::PRESENCE));
        self::assertTrue(PdfCatalog::isPublicDocument(PdfCatalog::RANKING));
        self::assertTrue(PdfCatalog::isPublicDocument(PdfCatalog::GAMES_GRID));
        self::assertFalse(PdfCatalog::isPublicDocument(PdfCatalog::CLUB_RANKING), 'published by profile 1 as a file, no public route yet');
        self::assertFalse(PdfCatalog::isPublicDocument(PdfCatalog::RULES_CATALOG), 'RCAT-11');
    }

    public function testEveryDocumentIsListedOnce(): void
    {
        $documents = PdfCatalog::documents();

        self::assertSame(array_values(array_unique($documents)), $documents);
        self::assertContains(PdfCatalog::RULES_CATALOG, $documents);
        self::assertContains(PdfCatalog::QRCODES, $documents, 'public only');
        self::assertSame(['visa' => 'ROLE_ORGANIZER', 'view=birthyear' => 'ROLE_ADMIN', 'category=U21' => 'ROLE_ORGANIZER'], PdfCatalog::optionRolesOf(PdfCatalog::PRESENCE));
        self::assertSame([], PdfCatalog::optionRolesOf(PdfCatalog::RANKING));
    }
}
