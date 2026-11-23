<?php

namespace App\Tests\Unit\Report;

use App\Report\ReportAnswers;
use App\Report\ReportField;
use App\Report\ReportScope;
use App\Report\ReportTypeCatalog;
use App\Report\ReportTypeDefinition;
use PHPUnit\Framework\TestCase;

/** Définitions du rapport de jury d'appel et de la fiche d'évaluation d'arbitrage (FEATURE_DESIGNATIONS.md D27, D28). */
final class AppealAndEvaluationDefinitionTest extends TestCase
{
    public function testD28TheJuryReportIsWrittenByThePresidentAndSentToTheJuryAndTheTeamsInCause(): void
    {
        $jury = ReportTypeCatalog::current(ReportTypeCatalog::JURY_APPEL);

        self::assertSame(ReportTypeDefinition::WRITERS_DELEGATE, $jury->writers);
        self::assertSame([ReportScope::Evenement, ReportScope::Journee], $jury->scopes);
        self::assertSame(['BUREAU_CNA', 'ROLE:RC', 'JURY', 'TEAMS_IN_CAUSE', 'AUTHOR'], $jury->recipients);
        self::assertSame('jury', $jury->fields()['jury']->rowsFrom);
    }

    public function testRap08TheEvaluationMarksMatchTheSheet(): void
    {
        $evaluation = ReportTypeCatalog::current(ReportTypeCatalog::EVALUATION_ARBITRAGE);
        $fields = $evaluation->fields();

        self::assertTrue($evaluation->restricted);
        self::assertSame([ReportScope::Match], $evaluation->scopes);
        $marks = array_filter($fields, static fn (ReportField $f): bool => $f->type === ReportField::SCORE);
        self::assertCount(20, $marks);
        self::assertSame(50, array_sum(array_map(static fn (ReportField $f): int => (int) $f->max, $marks)));
        foreach (['form', 'substance', 'game', 'laws', 'collaboration'] as $block) {
            self::assertSame(10, $fields[$block . '_total']->max, $block);
        }
        $full = array_map(static fn (ReportField $f): int => (int) $f->max, $marks);
        self::assertSame(50, ReportAnswers::normalize($evaluation, $full, false)['answers']['total']);
    }
}
