<?php

namespace App\Pdf\Document;

use App\Pdf\PdfCatalog;
use App\Pdf\PdfCompetitions;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use App\Pdf\Ranking\RankingTable;
use App\Pdf\Schema\SchemaLayout;
use App\Service\CompetitionSchemaService;

/**
 * Schema of a competition (`schema`, ex-print of the app4 Schema page, PDF_GOTENBERG.md § 7.3): a cup in stage
 * columns, a league by gameday. Options of the page: `count=0` hides the number of games, `times=0` the time slots.
 */
final class SchemaDocument implements PdfDocumentProvider
{
    private const HIDDEN = '0';

    public function __construct(
        private readonly CompetitionSchemaService $schemas,
        private readonly PdfCompetitions $competitions,
        private readonly PdfLabels $labels,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::SCHEMA;
    }

    public function supports(PdfTheme $theme): bool
    {
        return $theme === PdfTheme::Admin;
    }

    public function options(): array
    {
        return ['count', 'times'];
    }

    public function content(PdfRequest $request, PdfTheme $theme, ?array $allowedClubs): ?PdfContent
    {
        $code = $request->requireCompetition();
        $header = $this->competitions->header($request->season, $code);
        $schema = $this->schemas->schema($request->season, $code, $request->lang);
        if ($header === null || $schema === null || $schema['phases'] === []) {
            return null;
        }
        $cup = $header['competition']['type'] === RankingTable::CUP;

        return new PdfContent(
            template: 'schema.html.twig',
            title: $this->labels->forLanguage($request->lang)['schema.title'],
            filename: sprintf('%s_%s_%s_%s.pdf', $this->document(), $code, $request->season, $request->lang),
            variables: [
                'competition' => $header['competition'],
                'cup' => $cup,
                'stages' => $cup ? SchemaLayout::stages($schema['phases']) : [],
                'gamedays' => $cup ? [] : SchemaLayout::gamedays($schema['phases']),
                'total_games' => $schema['totalMatches'],
                'show_count' => $request->option('count') !== self::HIDDEN,
                'show_times' => $request->option('times') !== self::HIDDEN,
                'internal' => false,
            ],
            landscape: true,
            images: $header['images'],
        );
    }
}
