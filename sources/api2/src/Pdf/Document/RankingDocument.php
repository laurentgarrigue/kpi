<?php

namespace App\Pdf\Document;

use App\Pdf\PdfCatalog;
use App\Pdf\PdfCompetitions;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use App\Pdf\Ranking\RankingRepository;
use App\Pdf\Ranking\RankingSource;
use App\Pdf\Ranking\RankingTable;
use App\PublicResults\Dto\RankingRow;
use App\PublicResults\PublicCompetitionService;

/**
 * General ranking of a competition, CHPT / CP / MULTI (`ranking`, PDF_GOTENBERG.md § 7.4):
 * - public (ex-PdfCltChpt / PdfCltNiveau / PdfCltMulti): the published ranking, read through the service of the
 *   public website so that the PDF and the page always agree; reference mock-up of the public charter (§ 8.6);
 * - admin (ex-FeuilleCltChpt / FeuilleCltNiveau / FeuilleCltMulti): the computed ranking, « Provisional ».
 */
final class RankingDocument implements PdfDocumentProvider
{
    public function __construct(
        private readonly PublicCompetitionService $competitions,
        private readonly PdfCompetitions $headers,
        private readonly RankingRepository $rankings,
        private readonly PdfLabels $labels,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::RANKING;
    }

    public function supports(PdfTheme $theme): bool
    {
        return true;
    }

    public function options(): array
    {
        return [];
    }

    public function content(PdfRequest $request, PdfTheme $theme, ?array $allowedClubs): ?PdfContent
    {
        return $theme === PdfTheme::Public ? $this->published($request) : $this->computed($request);
    }

    private function published(PdfRequest $request): ?PdfContent
    {
        $code = $request->requireCompetition();
        $header = $this->competitions->findHeader($request->season, $code);
        $ranking = $this->competitions->ranking($request->season, $code);
        if ($header === null || $ranking === null || $ranking['rows'] === []) {
            return null;
        }

        // Visuals of the competition (§ 8.4.1); the ranking itself is the one of the public website.
        $visuals = $this->headers->publishedHeader($request->season, $code);
        if ($visuals === null) {
            return null;
        }

        return new PdfContent(
            template: 'ranking.html.twig',
            title: $this->labels->forLanguage($request->lang)['ranking.title'],
            filename: sprintf('%s_%s_%s_%s.pdf', $this->document(), $code, $request->season, $request->lang),
            variables: [
                'competition' => [...$visuals['competition'], 'title' => $header->displayTitle, 'subtitle' => $header->soustitre2],
                'rows' => self::rows($ranking['rows'], $ranking['qualified'], $ranking['eliminated']),
                'with_details' => $header->type !== 'MULTI',
            ],
            publicPath: sprintf('/competitions/%s/%s/ranking', rawurlencode($request->season), rawurlencode($code)),
            images: $visuals['images'],
        );
    }

    private function computed(PdfRequest $request): ?PdfContent
    {
        $code = $request->requireCompetition();
        $header = $this->headers->header($request->season, $code);
        if ($header === null) {
            return null;
        }
        $type = $header['competition']['type'];
        $rules = $header['rules'];
        $teams = $this->rankings->teams($request->season, $code, $type, RankingSource::Computed);
        $ranking = RankingTable::rankingRows($teams, $type, $rules['status'], $rules['final']);
        if ($ranking === []) {
            return null;
        }
        $unranked = RankingTable::hasUnrankedTeam($teams, $type);

        return new PdfContent(
            template: 'ranking.html.twig',
            title: $this->labels->forLanguage($request->lang)['ranking.title'],
            filename: sprintf('%s_%s_%s_%s.pdf', $this->document(), $code, $request->season, $request->lang),
            variables: [
                'competition' => $header['competition'],
                'rows' => self::rows($ranking, $unranked ? 0 : $rules['qualified'], $unranked ? 0 : $rules['eliminated']),
                'with_details' => $type !== 'MULTI',
                'provisional' => true,
                'internal' => true,
            ],
            images: $header['images'],
        );
    }

    /**
     * Rows of the PDF with their marks. Medals replace the « qualified » marks: a final ranking with medals has no
     * qualification left to show (validated on the reference mock-up, PDF_GOTENBERG.md § 7.4).
     *
     * @param list<RankingRow> $ranking
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(array $ranking, int $qualified, int $eliminated): array
    {
        $count = count($ranking);
        $hasMedals = array_filter($ranking, static fn (RankingRow $row): bool => $row->medal !== null) !== [];

        return array_map(static fn (RankingRow $row): array => [
            'rank' => $row->rank,
            'team' => $row->team->label,
            'points' => $row->points,
            'played' => $row->played,
            'details' => $row->details,
            'medal' => $row->medal,
            'qualified' => !$hasMedals && $row->rank <= $qualified,
            'eliminated' => $eliminated > 0 && $row->rank > $count - $eliminated,
        ], $ranking);
    }
}
