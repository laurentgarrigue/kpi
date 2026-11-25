<?php

namespace App\Pdf\Document;

use App\ClubRanking\ClubRankingRepository;
use App\ClubRanking\ClubRankingService;
use App\Pdf\InvalidPdfRequest;
use App\Pdf\PdfCatalog;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;

/**
 * National club ranking (`club-ranking`, PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 8 step 5), profile 1:
 * - `view=public` (default): the first N ranks of the latest publication (`version` for an older one), for diffusion;
 *   before any publication, the current ranking with the « provisional » watermark;
 * - `view=work`: every club and every rule of the current calculation, the scale with its interpretation notes and
 *   the adjustments, for the review by the CNA board.
 */
final class ClubRankingDocument implements PdfDocumentProvider
{
    private const VIEW_PUBLIC = 'public';
    private const VIEW_WORK = 'work';

    public function __construct(
        private readonly ClubRankingRepository $repository,
        private readonly ClubRankingService $service,
        private readonly PdfLabels $labels,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::CLUB_RANKING;
    }

    public function supports(PdfTheme $theme): bool
    {
        return $theme === PdfTheme::Admin;
    }

    public function options(): array
    {
        return ['view', 'version'];
    }

    public function content(PdfRequest $request, PdfTheme $theme, ?array $allowedClubs): ?PdfContent
    {
        $season = $this->repository->season($request->season);
        if ($season === null) {
            return null;
        }
        $view = $request->option('view') ?? self::VIEW_PUBLIC;
        if (!in_array($view, [self::VIEW_PUBLIC, self::VIEW_WORK], true)) {
            throw new InvalidPdfRequest('Option "view" must be "public" or "work".');
        }
        $labels = $this->labels->forLanguage($request->lang);

        return $view === self::VIEW_WORK ? $this->work($request, $labels) : $this->public($request, $season, $labels);
    }

    /**
     * @param array<string, mixed>  $season
     * @param array<string, string> $labels
     */
    private function public(PdfRequest $request, array $season, array $labels): ?PdfContent
    {
        $version = $request->option('version');
        $publication = $this->repository->publication($request->season, $version !== null && ctype_digit($version) ? (int) $version : null);
        $standings = $publication['standings'] ?? $this->service->standings($request->season);
        $topN = (int) ($publication['top_n'] ?? $season['top_n']);
        $rows = array_values(array_filter($standings, static fn (array $row): bool => $row['rank'] <= $topN));
        if ($rows === []) {
            return null;
        }

        return new PdfContent(
            template: 'sections.html.twig',
            title: $labels['club_ranking.title'],
            filename: sprintf('%s_%s_%s.pdf', $this->document(), $request->season, $request->lang),
            variables: [
                'columns' => [
                    ['key' => 'label', 'label' => $labels['club_ranking.club']],
                    ['key' => 'club', 'label' => $labels['club_ranking.number']],
                    ['key' => 'department', 'label' => $labels['club_ranking.department']],
                    ['key' => 'region', 'label' => $labels['club_ranking.region']],
                    ['key' => 'total', 'label' => $labels['club_ranking.points'], 'class' => 'num'],
                    ['key' => 'rank', 'label' => $labels['club_ranking.rank'], 'class' => 'num'],
                ],
                'sections' => [['rows' => $rows]],
                'provisional' => $publication === null,
                'note' => $publication === null ? null : sprintf('%s : %s', $labels['club_ranking.reference_date'], substr((string) $publication['reference_date'], 0, 10)),
            ],
        );
    }

    /** @param array<string, string> $labels */
    private function work(PdfRequest $request, array $labels): PdfContent
    {
        $rules = array_map(static fn (array $rule): array => [
            'code' => $rule['article'] . '.' . $rule['position'], 'short' => $rule['short_label'], 'label' => $rule['label'], 'points' => $rule['points'], 'note' => $rule['note'],
        ], $this->repository->ruleRows($request->season));
        $columns = [
            ['key' => 'rank', 'label' => $labels['club_ranking.rank'], 'class' => 'num'],
            ['key' => 'label', 'label' => $labels['club_ranking.club']],
            ...array_map(static fn (array $rule): array => ['key' => 'r' . $rule['code'], 'label' => $rule['code'], 'class' => 'num'], $rules),
            ['key' => 'total', 'label' => $labels['club_ranking.total'], 'class' => 'num pdf-strong'],
        ];
        $rows = array_map(static function (array $row) use ($rules): array {
            $cells = ['rank' => $row['rank'], 'label' => $row['label'], 'total' => $row['total']];
            foreach ($rules as $rule) {
                $cells['r' . $rule['code']] = $row['by_rule'][$rule['code']] ?? '';
            }

            return $cells;
        }, $this->service->standings($request->season));
        $adjustments = array_map(static fn ($adjustment): array => [
            'code' => $adjustment->ruleCode, 'label' => $adjustment->clubCode, 'points' => $adjustment->points, 'note' => $adjustment->reason,
        ], $this->repository->adjustments($request->season));
        $scaleColumns = [
            ['key' => 'code', 'label' => $labels['club_ranking.code']],
            ['key' => 'label', 'label' => $labels['club_ranking.label']],
            ['key' => 'points', 'label' => $labels['club_ranking.points'], 'class' => 'num'],
            ['key' => 'note', 'label' => $labels['club_ranking.note']],
        ];

        return new PdfContent(
            template: 'sections.html.twig',
            title: $labels['club_ranking.title'],
            filename: sprintf('%s_%s_work_%s.pdf', $this->document(), $request->season, $request->lang),
            variables: [
                'columns' => $columns,
                'sections' => [
                    ['rows' => $rows],
                    ['title' => $labels['club_ranking.scale'], 'columns' => $scaleColumns, 'rows' => $rules, 'new_page' => true],
                    ...($adjustments === [] ? [] : [['title' => $labels['club_ranking.adjustments'], 'columns' => [...array_slice($scaleColumns, 0, 1), ['key' => 'label', 'label' => $labels['club_ranking.club']], $scaleColumns[2], ['key' => 'note', 'label' => $labels['club_ranking.reason']]], 'rows' => $adjustments]]),
                ],
                'dense' => true,
                'internal' => true,
                'provisional' => true,
            ],
            landscape: true,
        );
    }
}
