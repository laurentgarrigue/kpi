<?php

namespace App\Pdf\Document;

use App\Pdf\InvalidPdfRequest;
use App\Pdf\PdfCatalog;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use App\RuleCatalog\RuleCatalogDescriber;
use App\RuleCatalog\RuleCatalogDescription;
use App\RuleCatalog\RuleCatalogs;
use Psr\Clock\ClockInterface;

/**
 * Values of the business rule catalogues (`rules-catalog`, FEATURE_RULES_CATALOG_PDF.md § 4), profile 1: a table of
 * contents then every catalogue on its own page, or one catalogue (`catalog=<code>`). Each catalogue opens with its
 * season, version, source files and edition date.
 */
final class RulesCatalogDocument implements PdfDocumentProvider
{
    private const OPTION_CATALOG = 'catalog';
    private const EDITION_FORMAT = 'd/m/Y H:i';
    private const NONE = '—';

    public function __construct(
        private readonly RuleCatalogs $catalogs,
        private readonly PdfLabels $labels,
        private readonly ClockInterface $clock,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::RULES_CATALOG;
    }

    public function supports(PdfTheme $theme): bool
    {
        return $theme === PdfTheme::Admin;
    }

    public function options(): array
    {
        return [self::OPTION_CATALOG];
    }

    public function content(PdfRequest $request, PdfTheme $theme, ?array $allowedClubs): ?PdfContent
    {
        $code = $request->option(self::OPTION_CATALOG);
        $describers = $code === null ? $this->catalogs->all() : [$code => $this->catalogs->find($code)
            ?? throw new InvalidPdfRequest(sprintf('Unknown catalogue "%s".', $code))];
        if ($describers === []) {
            return null;
        }
        $labels = $this->labels->forLanguage($request->lang);
        $descriptions = array_map(static fn (RuleCatalogDescriber $describer): RuleCatalogDescription => $describer->describe($request->season), $describers);
        $editedOn = $this->clock->now()->setTimezone(new \DateTimeZone($request->timezone))->format(self::EDITION_FORMAT);

        $sections = $code === null ? [$this->contents($descriptions, $labels)] : [];
        foreach ($descriptions as $description) {
            $sections[] = [
                'title' => $description->title,
                'pairs' => [
                    [$labels['season'], $request->season],
                    [$labels['rules_catalog.version'], $description->version ?? self::NONE],
                    [$labels['rules_catalog.sources'], implode(', ', $description->sources)],
                    [$labels['generated_on'], $editedOn],
                ],
                'new_page' => $sections !== [],
            ];
            array_push($sections, ...$description->sections);
        }

        return new PdfContent(
            template: 'sections.html.twig',
            title: $labels['rules_catalog.title'],
            filename: sprintf('%s_%s%s_%s.pdf', $this->document(), $request->season, $code === null ? '' : '_' . $code, $request->lang),
            variables: ['columns' => [], 'sections' => $sections, 'dense' => true, 'internal' => true],
        );
    }

    /**
     * @param array<string, RuleCatalogDescription> $descriptions
     * @param array<string, string>                 $labels
     *
     * @return array<string, mixed>
     */
    private function contents(array $descriptions, array $labels): array
    {
        $rows = [];
        foreach ($descriptions as $code => $description) {
            $rows[] = [
                'number' => count($rows) + 1,
                'catalog' => $description->title,
                'code' => $code,
                'version' => $description->version ?? self::NONE,
                'sources' => implode(', ', $description->sources),
            ];
        }

        return [
            'title' => $labels['rules_catalog.contents'],
            'columns' => [
                ['key' => 'number', 'label' => $labels['rules_catalog.number'], 'class' => 'num'],
                ['key' => 'catalog', 'label' => $labels['rules_catalog.catalog']],
                ['key' => 'code', 'label' => $labels['rules_catalog.code']],
                ['key' => 'version', 'label' => $labels['rules_catalog.version']],
                ['key' => 'sources', 'label' => $labels['rules_catalog.sources']],
            ],
            'rows' => $rows,
        ];
    }
}
