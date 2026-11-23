<?php

namespace App\Report;

use App\Pdf\PdfContent;
use App\Pdf\PdfRenderer;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;

/**
 * PDF d'un rapport officiel (soumission figée) et PDF vierge d'un type (D17, D29, RAP-10), avec le gabarit générique
 * `sections.html.twig` : même sections que le formulaire, données KPI en tête.
 */
final class ReportPdf
{
    public function __construct(private readonly PdfRenderer $renderer)
    {
    }

    /**
     * @param array<string, mixed> $answers
     * @param array<string, mixed> $kpiData
     */
    public function render(ReportTypeDefinition $definition, string $season, string $lang, array $answers, array $kpiData, string $filename, ?string $status = null): string
    {
        return $this->renderer->render($this->content($definition, $lang, $answers, $kpiData, $filename, $status), PdfTheme::Admin, PdfRequest::fromQuery(['season' => $season, 'lang' => $lang]));
    }

    public function blank(ReportTypeDefinition $definition, string $season, string $lang): string
    {
        return $this->render($definition, $season, $lang, [], [], sprintf('%s_v%d_vierge.pdf', strtolower($definition->code), $definition->version));
    }

    /**
     * @param array<string, mixed> $answers
     * @param array<string, mixed> $kpiData
     */
    public function content(ReportTypeDefinition $definition, string $lang, array $answers, array $kpiData, string $filename, ?string $status = null): PdfContent
    {
        $en = $lang === 'en';
        $sections = [];
        $header = self::headerPairs($kpiData, $en);
        if ($status !== null) {
            $header[] = [$en ? 'Status' : 'État', $status];
        }
        if ($header !== []) {
            $sections[] = ['title' => $en ? 'KPI data' : 'Données KPI', 'pairs' => $header];
        }
        foreach ($definition->sections as $section) {
            $sections[] = [
                'title' => $en ? $section->titleEn : $section->titleFr,
                'pairs' => array_map(static fn (ReportField $field): array => [
                    ($en ? $field->labelEn : $field->labelFr) . ($field->required ? ' *' : ''),
                    self::display($field, $answers[$field->key] ?? null, $en),
                ], $section->fields),
            ];
        }

        // « columns » est lu par le gabarit même sans tableau (Twig strict_variables)
        return new PdfContent('sections.html.twig', $en ? $definition->labelEn : $definition->labelFr, $filename, ['sections' => $sections, 'columns' => [], 'internal' => true]);
    }

    /**
     * @param array<string, mixed> $kpiData
     *
     * @return list<array{0: string, 1: string}>
     */
    private static function headerPairs(array $kpiData, bool $en): array
    {
        $pairs = [];
        if (isset($kpiData['label'])) {
            $pairs[] = [$en ? 'Perimeter' : 'Périmètre', (string) $kpiData['label']];
        }
        if (!empty($kpiData['competitions'])) {
            $pairs[] = [$en ? 'Competitions' : 'Compétitions', implode(', ', $kpiData['competitions'])];
        }
        if (!empty($kpiData['start'])) {
            $pairs[] = [$en ? 'Dates' : 'Dates', trim(sprintf('%s – %s', $kpiData['start'], $kpiData['end'] ?? ''), ' –')];
        }
        if (!empty($kpiData['places'])) {
            $pairs[] = [$en ? 'Place' : 'Lieu', implode(', ', $kpiData['places'])];
        }
        if (isset($kpiData['match']) && is_array($kpiData['match'])) {
            $match = $kpiData['match'];
            $pairs[] = [$en ? 'Game' : 'Match', sprintf('n° %s · %s - %s · %s - %s', $match['number'] ?? '', $match['teams'][0] ?? '', $match['teams'][1] ?? '', $match['score'][0] ?? '', $match['score'][1] ?? '')];
            $pairs[] = [$en ? 'Referees' : 'Arbitres', implode(', ', $match['referees'] ?? [])];
        }
        if (isset($kpiData['author'])) {
            $pairs[] = [$en ? 'Author' : 'Auteur', (string) $kpiData['author']];
        }

        return $pairs;
    }

    private static function display(ReportField $field, mixed $value, bool $en): string
    {
        if ($value === null || $value === '') {
            return $field->type === ReportField::SCORE || $field->type === ReportField::SUM ? sprintf('… / %d', (int) $field->max) : '';
        }

        return match ($field->type) {
            ReportField::CHECKBOX => $value ? ($en ? 'Yes' : 'Oui') : ($en ? 'No' : 'Non'),
            ReportField::SCORE, ReportField::SUM => sprintf('%s / %d', $value, (int) $field->max),
            default => (string) $value,
        };
    }
}
