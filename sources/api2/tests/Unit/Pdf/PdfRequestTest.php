<?php

namespace App\Tests\Unit\Pdf;

use App\Pdf\InvalidPdfRequest;
use App\Pdf\PdfRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** PDF-SESS-01 / PDF-SESS-02: the whole context is in the query, validated (PDF_GOTENBERG.md § 6.1). */
final class PdfRequestTest extends TestCase
{
    public function testPdfSess01TheQueryIsTheWholeContext(): void
    {
        $request = PdfRequest::fromQuery(
            ['season' => '2026', 'competition' => 'N1H', 'team' => '12', 'games' => '3,1,3', 'lang' => 'en', 'tz' => 'Europe/London', 'view' => 'teams'],
            ['view'],
        );

        self::assertSame('2026', $request->season);
        self::assertSame('N1H', $request->requireCompetition());
        self::assertSame(12, $request->team);
        self::assertSame([3, 1], $request->games, 'duplicates removed, order kept');
        self::assertSame('en', $request->lang);
        self::assertSame('Europe/London', $request->timezone);
        self::assertSame('teams', $request->option('view'));
        self::assertSame(
            ['season' => '2026', 'competition' => 'N1H', 'team' => 12, 'games' => '3,1', 'lang' => 'en', 'tz' => 'Europe/London', 'view' => 'teams'],
            $request->toQuery(),
        );
    }

    public function testSeveralCompetitionsAsTheLegacyCompetList(): void
    {
        $request = PdfRequest::fromQuery(['season' => '2026', 'competition' => 'N1H,N2H,N1H']);

        self::assertSame(['N1H', 'N2H'], $request->competitions);
        self::assertNull($request->competition, 'not one competition');
        self::assertSame('N1H,N2H', $request->toQuery()['competition']);
        $this->expectException(InvalidPdfRequest::class);
        $request->requireCompetition();
    }

    public function testDefaultsAreFrenchAndParis(): void
    {
        $request = PdfRequest::fromQuery(['season' => '2026']);

        self::assertSame('fr', $request->lang);
        self::assertSame(PdfRequest::DEFAULT_TIMEZONE, $request->timezone);
        self::assertSame([], $request->games);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidQueries(): iterable
    {
        yield 'season missing' => [['competition' => 'N1H']];
        yield 'season malformed' => [['season' => '26']];
        yield 'unknown parameter' => [['season' => '2026', 'Compet' => 'N1H']];
        yield 'option of another document' => [['season' => '2026', 'visa' => '1']];
        yield 'competition malformed' => [['season' => '2026', 'competition' => "N1H'--"]];
        yield 'team not a positive integer' => [['season' => '2026', 'team' => '-3']];
        yield 'games not integers' => [['season' => '2026', 'games' => '1,x']];
        yield 'too many games' => [['season' => '2026', 'games' => implode(',', range(1, PdfRequest::MAX_GAMES + 1))]];
        yield 'unknown language' => [['season' => '2026', 'lang' => 'de']];
        yield 'unknown time zone' => [['season' => '2026', 'tz' => 'Mars/Olympus']];
        yield 'array value' => [['season' => ['2026']]];
    }

    /** @param array<string, mixed> $query */
    #[DataProvider('invalidQueries')]
    public function testPdfSess02InvalidQueriesAreRefused(array $query): void
    {
        $this->expectException(InvalidPdfRequest::class);

        PdfRequest::fromQuery($query);
    }

    public function testACompetitionIsRequiredOnlyByTheDocumentsNeedingIt(): void
    {
        $this->expectException(InvalidPdfRequest::class);

        PdfRequest::fromQuery(['season' => '2026'])->requireCompetition();
    }
}
