<?php

namespace App\Pdf\Document;

use App\Pdf\Games\GamesGrid;
use App\Pdf\Games\GamesScope;
use App\Pdf\Games\GamesSort;
use App\Pdf\InvalidPdfRequest;
use App\Pdf\PdfCatalog;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use App\Pdf\Ranking\RankingTable;

/**
 * Pitch grid (`games-grid`, ex-PdfListeMatchs4Terrains… / 5TerrainsEn[Teams], PDF_GOTENBERG.md § 7.3.1), public only:
 * two views, `teams` (default) and `phases`, one column per pitch actually used (GamesGrid).
 */
final class GamesGridDocument implements PdfDocumentProvider
{
    public function __construct(
        private readonly GamesScope $scope,
        private readonly PdfLabels $labels,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::GAMES_GRID;
    }

    public function supports(PdfTheme $theme): bool
    {
        return $theme === PdfTheme::Public;
    }

    public function options(): array
    {
        return ['view', ...array_diff(GamesScope::FILTER_OPTIONS, ['sort'])];
    }

    public function content(PdfRequest $request, PdfTheme $theme, ?array $allowedClubs): ?PdfContent
    {
        $view = $request->option('view') ?? GamesGrid::VIEWS[0];
        if (!in_array($view, GamesGrid::VIEWS, true)) {
            throw new InvalidPdfRequest('Parameter "view" must be "teams" or "phases" (PDF-DATA-07).');
        }
        $games = $this->scope->games($request, $theme, GamesSort::Date);
        $header = $games === [] ? null : $this->scope->header($request, $theme, $games);
        if ($header === null) {
            return null;
        }
        $labels = $this->labels->forLanguage($request->lang);

        return new PdfContent(
            template: 'sections.html.twig',
            title: $labels['games.grid_title'],
            filename: sprintf('%s-%s_%s_%s.pdf', $this->document(), $view, $request->season, $request->lang),
            variables: [
                'competition' => $header['competition'],
                'columns' => [],
                'sections' => GamesGrid::sections($games, $view, $labels, RankingTable::gamedayLabel(...)),
                'grid' => true,
            ],
            landscape: true,
            images: $header['images'],
        );
    }
}
