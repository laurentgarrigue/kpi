<?php

namespace App\Tests\Unit\Report;

use App\Report\ReportAnswers;
use App\Report\ReportScope;
use App\Report\ReportTypeCatalog;
use App\Report\ReportTypeDefinition;
use PHPUnit\Framework\TestCase;

/** Définition du bilan de journée (FEATURE_DESIGNATIONS.md D1, D18, D25, D28 ; § 7). */
final class GamedayReportDefinitionTest extends TestCase
{
    public function testD25TheSectionsOfTheReport(): void
    {
        $bilan = ReportTypeCatalog::current(ReportTypeCatalog::BILAN_JOURNEE);

        self::assertSame(
            ['officials', 'representatives', 'general', 'sport', 'refereeing', 'logistics', 'retributions'],
            array_map(static fn ($section) => $section->key, $bilan->sections),
        );
        self::assertArrayHasKey('camping_available', $bilan->fields());
        self::assertSame(36, $bilan->fields()['site_total']->max);
    }

    public function testD1AndD28ScopesWritersRecipientsAndDeadline(): void
    {
        $bilan = ReportTypeCatalog::current(ReportTypeCatalog::BILAN_JOURNEE);

        self::assertSame([ReportScope::Evenement, ReportScope::Journee], $bilan->scopes);
        self::assertSame(ReportTypeDefinition::WRITERS_DELEGATE, $bilan->writers);
        self::assertTrue($bilan->uniquePerScope);
        self::assertTrue($bilan->gamedayOnlyOutsideEvent);
        self::assertSame(['BUREAU_CNA', 'ROLE:RC', 'AUTHOR'], $bilan->recipients);
        self::assertSame(3, $bilan->deadlineDays);
    }

    public function testTableRowsAreCheckedColumnByColumnAndEmptyRowsDropped(): void
    {
        $bilan = ReportTypeCatalog::current(ReportTypeCatalog::BILAN_JOURNEE);

        $ok = ReportAnswers::normalize($bilan, ['infractions' => [['offender' => 'Club X', 'category' => '2'], []]], false);
        self::assertSame([[], [['offender' => 'Club X', 'category' => '2']]], [$ok['errors'], $ok['answers']['infractions']]);
        $bad = ReportAnswers::normalize($bilan, ['infractions' => [['category' => '7']]], false);
        self::assertSame(['infractions' => 'invalid'], $bad['errors']);
        self::assertSame(['infractions' => 'invalid'], ReportAnswers::normalize($bilan, ['infractions' => 'Club X'], false)['errors']);
    }
}
