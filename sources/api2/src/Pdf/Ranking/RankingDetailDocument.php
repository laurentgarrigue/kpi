<?php

namespace App\Pdf\Ranking;

use App\Pdf\PdfCompetitions;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;

/**
 * Detail documents of a ranking (by team, by gameday, by phase, PDF_GOTENBERG.md § 7.4), public and admin: the public
 * route reads the published ranking of a published competition, the admin route the computed one (« Provisional »).
 */
abstract class RankingDetailDocument implements PdfDocumentProvider
{
    public function __construct(
        protected readonly RankingRepository $rankings,
        private readonly PdfCompetitions $competitions,
        private readonly PdfLabels $labels,
    ) {
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
        $code = $request->requireCompetition();
        $header = $theme === PdfTheme::Public
            ? $this->competitions->publishedHeader($request->season, $code)
            : $this->competitions->header($request->season, $code);
        if ($header === null) {
            return null;
        }
        $source = $theme === PdfTheme::Public ? RankingSource::Published : RankingSource::Computed;
        $labels = $this->labels->forLanguage($request->lang);
        $sections = $this->sections($request->season, $code, $header['competition']['type'], $header['rules'], $source, $labels);
        if ($sections === []) {
            return null;
        }

        return new PdfContent(
            template: 'sections.html.twig',
            title: $labels[$this->titleLabel()],
            filename: sprintf('%s_%s_%s_%s.pdf', $this->document(), $code, $request->season, $request->lang),
            variables: [
                'competition' => $header['competition'],
                'columns' => RankingTable::columns($labels, $header['rules']['forfeits']),
                'sections' => $sections,
                'dense' => true,
                'provisional' => $source->isProvisional(),
                'internal' => $source->isProvisional(),
            ],
            images: $header['images'],
        );
    }

    /** Key of the title in config/pdf/labels.yaml. */
    abstract protected function titleLabel(): string;

    /**
     * Sections of the document; a section without `columns` is a ranking table.
     *
     * @param array{status: string, final: bool, qualified: int, eliminated: int, published: bool, forfeits: bool} $rules
     * @param array<string, string>                                                                              $labels
     *
     * @return list<array<string, mixed>>
     */
    abstract protected function sections(string $season, string $code, string $type, array $rules, RankingSource $source, array $labels): array;
}
