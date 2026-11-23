<?php

namespace App\Tests\Unit\Report;

use App\Report\ReportAnswers;
use App\Report\ReportField;
use App\Report\ReportScope;
use App\Report\ReportSection;
use App\Report\ReportTypeCatalog;
use App\Report\ReportTypeDefinition;
use PHPUnit\Framework\TestCase;

/** Réponses d'un rapport officiel (FEATURE_DESIGNATIONS.md D13, D18, RAP-02, RAP-06, RAP-08). */
final class ReportAnswersTest extends TestCase
{
    public function testADraftMayBeIncompleteButASubmissionNeedsTheRequiredFields(): void
    {
        $definition = ReportTypeCatalog::current(ReportTypeCatalog::INCIDENT);

        self::assertSame([], ReportAnswers::normalize($definition, ['place' => 'Bassin'], false)['errors']);
        self::assertSame(
            ['date' => 'required', 'description' => 'required'],
            ReportAnswers::normalize($definition, ['place' => 'Bassin'], true)['errors'],
        );
    }

    public function testValuesAreCheckedByType(): void
    {
        $definition = ReportTypeCatalog::current(ReportTypeCatalog::INCIDENT);

        $result = ReportAnswers::normalize($definition, [
            'date' => '2998-02-30', 'time' => '25:00', 'jury_requested' => 'oui', 'description' => '  Faits  ', 'unknown' => 1,
        ], false);

        self::assertSame(['unknown' => 'unknown', 'date' => 'invalid', 'time' => 'invalid', 'jury_requested' => 'invalid'], $result['errors']);
        self::assertSame(['description' => 'Faits'], $result['answers']);
    }

    public function testRap08AComputedSumIsNeverTypedAndAlwaysRecomputed(): void
    {
        $definition = new ReportTypeDefinition('TEST', 1, 'Test', 'Test', [ReportScope::Match], [], [
            new ReportSection('notes', 'Notes', 'Marks', [
                new ReportField('a', ReportField::SCORE, 'A', 'A', max: 10),
                new ReportField('b', ReportField::SCORE, 'B', 'B', max: 10),
                new ReportField('total', ReportField::SUM, 'Total', 'Total', sumOf: ['a', 'b']),
            ]),
        ]);

        $result = ReportAnswers::normalize($definition, ['a' => 7, 'b' => 11], false);

        self::assertSame(['b' => 'invalid'], $result['errors']);
        self::assertSame(['a' => 7, 'total' => 7], $result['answers']);
        $typed = ReportAnswers::normalize($definition, ['a' => 3, 'total' => 50], false);
        self::assertSame([[], 3], [$typed['errors'], $typed['answers']['total']]);
    }

    public function testRap02EveryVersionOfATypeStaysAvailable(): void
    {
        $current = ReportTypeCatalog::current(ReportTypeCatalog::INCIDENT);

        self::assertSame($current, ReportTypeCatalog::version(ReportTypeCatalog::INCIDENT, $current->version));
        self::assertTrue($current->allowsScope(ReportScope::Match));
        self::assertSame(['ROLE:DELEGUE_CNA', 'ROLE:JUGE_ARBITRE', 'ROLE:RC', 'AUTHOR'], $current->recipients);
        $this->expectException(\InvalidArgumentException::class);
        ReportTypeCatalog::version(ReportTypeCatalog::INCIDENT, 999);
    }
}
