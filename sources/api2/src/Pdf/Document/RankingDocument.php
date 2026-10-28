<?php

namespace App\Pdf\Document;

use App\Pdf\PdfAssets;
use App\Pdf\PdfCatalog;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use App\PublicResults\Dto\RankingRow;
use App\PublicResults\PublicCompetitionService;

/**
 * General ranking of a competition, CHPT / CP / MULTI (`ranking`, ex-PdfCltChpt / PdfCltNiveau / PdfCltMulti,
 * PDF_GOTENBERG.md § 7.4). Public version only for now: the published ranking, read through the service of the
 * public website so that the PDF and the page always agree. Reference mock-up of the public charter (§ 8.6).
 */
final class RankingDocument implements PdfDocumentProvider
{
    public function __construct(
        private readonly PublicCompetitionService $competitions,
        private readonly PdfAssets $assets,
        private readonly PdfLabels $labels,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::RANKING;
    }

    public function supports(PdfTheme $theme): bool
    {
        return $theme === PdfTheme::Public;
    }

    public function options(): array
    {
        return [];
    }

    public function content(PdfRequest $request, PdfTheme $theme, ?array $allowedClubs): ?PdfContent
    {
        $code = $request->requireCompetition();
        $header = $this->competitions->findHeader($request->season, $code);
        $ranking = $this->competitions->ranking($request->season, $code);
        if ($header === null || $ranking === null || $ranking['rows'] === []) {
            return null;
        }

        $rows = self::rows($ranking['rows'], $ranking['qualified'], $ranking['eliminated']);

        $logo = $this->assets->legacyImage($header->logo);

        return new PdfContent(
            template: 'ranking.html.twig',
            title: $this->labels->forLanguage($request->lang)['ranking.title'],
            filename: sprintf('%s_%s_%s_%s.pdf', $this->document(), $code, $request->season, $request->lang),
            variables: [
                'competition' => [
                    'code' => $code,
                    'title' => $header->displayTitle,
                    'subtitle' => $header->soustitre2,
                    'type' => $header->type,
                    'logo' => $logo['name'] ?? null,
                ],
                'rows' => $rows,
                'with_details' => $header->type !== 'MULTI',
            ],
            publicPath: sprintf('/competitions/%s/%s/ranking', rawurlencode($request->season), rawurlencode($code)),
            images: $logo === null ? [] : [$logo['name'] => $logo['content']],
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
