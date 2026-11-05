<?php

namespace App\Tests\Unit\Pdf\Document;

use App\Pdf\Document\RankingDocument;
use App\PublicResults\Dto\RankingRow;
use App\PublicResults\Dto\TeamRef;
use PHPUnit\Framework\TestCase;

/** Marks of the public ranking PDF (PDF_GOTENBERG.md § 7.4). */
final class RankingDocumentTest extends TestCase
{
    private static function row(int $rank, ?int $medal = null): RankingRow
    {
        return new RankingRow($rank, new TeamRef($rank, null, 'Team ' . $rank, null), 10 - $rank, 3, $medal);
    }

    public function testQualifiedAndEliminatedMarks(): void
    {
        $rows = RankingDocument::rows([self::row(1), self::row(2), self::row(3), self::row(4)], 2, 1);

        self::assertSame([true, true, false, false], array_column($rows, 'qualified'));
        self::assertSame([false, false, false, true], array_column($rows, 'eliminated'));
    }

    public function testMedalsReplaceTheQualifiedMarks(): void
    {
        $rows = RankingDocument::rows([self::row(1, 1), self::row(2, 2), self::row(3, 3), self::row(4)], 2, 0);

        self::assertSame([false, false, false, false], array_column($rows, 'qualified'));
        self::assertSame([1, 2, 3, null], array_column($rows, 'medal'));
    }
}
