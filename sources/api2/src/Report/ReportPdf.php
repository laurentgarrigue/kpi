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
    /** Lignes vides d'un tableau sur le PDF vierge. */
    private const BLANK_ROWS = 5;

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
            $pairs = [];
            $tables = [];
            foreach ($section->fields as $field) {
                $label = ($en ? $field->labelEn : $field->labelFr) . ($field->required ? ' *' : '');
                if ($field->type === ReportField::TABLE) {
                    $tables[] = self::table($field, $label, $answers[$field->key] ?? [], $en);
                } else {
                    $pairs[] = [$label, self::display($field, $answers[$field->key] ?? null, $en)];
                }
            }
            $sections[] = ['title' => $en ? $section->titleEn : $section->titleFr, 'pairs' => $pairs];
            array_push($sections, ...$tables);
        }
        array_push($sections, ...self::kpiTables($kpiData, $en));

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
        if (isset($kpiData['matches']['planned'])) {
            $pairs[] = [$en ? 'Games planned / played' : 'Matchs prévus / joués', sprintf('%d / %d', $kpiData['matches']['planned'], $kpiData['matches']['played'])];
        }
        if (isset($kpiData['author'])) {
            $pairs[] = [$en ? 'Author' : 'Auteur', (string) $kpiData['author']];
        }

        return $pairs;
    }

    /**
     * Un champ tableau devient une section à lignes ; vierge, il garde 5 lignes à remplir à la main.
     *
     * @return array<string, mixed>
     */
    private static function table(ReportField $field, string $label, mixed $rows, bool $en): array
    {
        $rows = is_array($rows) && $rows !== [] ? $rows : array_fill(0, self::BLANK_ROWS, []);

        return [
            'subtitle' => $label,
            'columns' => array_map(static fn (ReportField $column): array => ['key' => $column->key, 'label' => $en ? $column->labelEn : $column->labelFr], $field->columns),
            'rows' => array_map(static fn (array $row): array => array_combine(
                array_map(static fn (ReportField $column): string => $column->key, $field->columns),
                array_map(static fn (ReportField $column): string => self::display($column, $row[$column->key] ?? null, $en), $field->columns),
            ), $rows),
        ];
    }

    /**
     * Matchs, cartons par compétition et rapports liés (sections 5, 6 et 9 du bilan, § 7).
     *
     * @param array<string, mixed> $kpiData
     *
     * @return list<array<string, mixed>>
     */
    private static function kpiTables(array $kpiData, bool $en): array
    {
        $sections = [];
        if (!empty($kpiData['cards'])) {
            $sections[] = [
                'title' => $en ? 'Cards (KPI)' : 'Cartons (KPI)',
                'columns' => [
                    ['key' => 'competition', 'label' => $en ? 'Competition' : 'Compétition'],
                    ['key' => 'green', 'label' => $en ? 'Green' : 'Verts'],
                    ['key' => 'yellow', 'label' => $en ? 'Yellow' : 'Jaunes'],
                    ['key' => 'red', 'label' => $en ? 'Red' : 'Rouges'],
                    ['key' => 'black', 'label' => $en ? 'Black' : 'Noirs'],
                ],
                'rows' => array_map(static fn (string $code, array $cards): array => ['competition' => $code] + $cards, array_keys($kpiData['cards']), $kpiData['cards']),
            ];
        }
        if (!empty($kpiData['linked'])) {
            $sections[] = [
                'title' => $en ? 'Linked reports' : 'Pièces liées',
                'pairs' => array_map(static fn (array $report): array => [sprintf('%s n° %d', $report['type'], $report['id']), sprintf('%s %s · %s', $report['scopeType'], $report['scopeId'], $report['state'])], $kpiData['linked']),
            ];
        }

        return $sections;
    }

    private static function display(ReportField $field, mixed $value, bool $en): string
    {
        if ($value === null || $value === '') {
            return $field->type === ReportField::SCORE || $field->type === ReportField::SUM ? sprintf('… / %d', (int) $field->max) : '';
        }

        return match ($field->type) {
            ReportField::TABLE => '',
            ReportField::CHECKBOX => $value ? ($en ? 'Yes' : 'Oui') : ($en ? 'No' : 'Non'),
            ReportField::SCORE, ReportField::SUM => sprintf('%s / %d', $value, (int) $field->max),
            default => (string) $value,
        };
    }
}
