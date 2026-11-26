<?php

namespace App\Tests\Unit\Pdf\Document;

use App\Pdf\Document\RulesCatalogDocument;
use App\Pdf\InvalidPdfRequest;
use App\Pdf\PdfContent;
use App\Pdf\PdfLabels;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use App\RuleCatalog\RuleCatalogDescriber;
use App\RuleCatalog\RuleCatalogDescription;
use App\RuleCatalog\RuleCatalogs;
use App\RuleCatalog\RuleSection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/** The `rules-catalog` document (FEATURE_RULES_CATALOG_PDF.md § 4), on two fake catalogues. */
final class RulesCatalogDocumentTest extends TestCase
{
    public function testRcat08TableOfContentsThenEveryCatalogueOnANewPage(): void
    {
        $sections = $this->content(['season' => '2027'])->variables['sections'];

        self::assertSame('Sommaire', $sections[0]['title']);
        self::assertSame(['Alpha', 'Bêta'], array_column($sections[0]['rows'], 'catalog'));
        self::assertSame(['alpha', 'beta'], array_column($sections[0]['rows'], 'code'));
        self::assertSame(['v2', '—'], array_column($sections[0]['rows'], 'version'));
        self::assertSame(['Alpha', 'Valeurs alpha', 'Bêta', 'Valeurs bêta'], array_slice(array_column($sections, 'title'), 1));
        self::assertTrue($sections[1]['new_page']);
        self::assertTrue($sections[3]['new_page']);
    }

    public function testRcat08OneCatalogueWithoutTableOfContents(): void
    {
        $content = $this->content(['season' => '2027', 'catalog' => 'beta']);
        $sections = $content->variables['sections'];

        self::assertSame(['Bêta', 'Valeurs bêta'], array_column($sections, 'title'));
        self::assertFalse($sections[0]['new_page']);
        self::assertSame('rules-catalog_2027_beta_fr.pdf', $content->filename);
        self::assertTrue($content->variables['internal']);
    }

    public function testRcat09EachCatalogueOpensWithSeasonVersionSourcesAndEditionDate(): void
    {
        $sections = $this->content(['season' => '2027', 'catalog' => 'alpha'])->variables['sections'];

        self::assertSame([
            ['Saison', '2027'],
            ['Version', 'v2'],
            ['Fichiers source', 'sources/alpha.php, table kp_alpha'],
            ['Édité le', '25/11/2026 11:30'],
        ], $sections[0]['pairs'], 'edition date in the time zone of the request');
    }

    public function testRcat10UnknownCatalogueIsRefused(): void
    {
        $this->expectException(InvalidPdfRequest::class);
        $this->content(['season' => '2027', 'catalog' => 'gamma']);
    }

    public function testAdminOnly(): void
    {
        $document = $this->document();

        self::assertTrue($document->supports(PdfTheme::Admin));
        self::assertFalse($document->supports(PdfTheme::Public));
    }

    /** @param array<string, string> $query */
    private function content(array $query): PdfContent
    {
        $document = $this->document();
        $content = $document->content(PdfRequest::fromQuery($query, $document->options()), PdfTheme::Admin, null);
        self::assertNotNull($content);

        return $content;
    }

    private function document(): RulesCatalogDocument
    {
        return new RulesCatalogDocument(
            new RuleCatalogs([
                self::describer('alpha', new RuleCatalogDescription('Alpha', 'v2', ['sources/alpha.php', 'table kp_alpha'], [RuleSection::lines('Valeurs alpha', ['a'])])),
                self::describer('beta', new RuleCatalogDescription('Bêta', null, ['sources/beta.php'], [RuleSection::pairs('Valeurs bêta', ['b' => 1])])),
            ]),
            new PdfLabels(__DIR__ . '/../../../../config/pdf/labels.yaml'),
            new MockClock('2026-11-25 10:30:00', 'UTC'),
        );
    }

    private static function describer(string $code, RuleCatalogDescription $description): RuleCatalogDescriber
    {
        return new class($code, $description) implements RuleCatalogDescriber {
            public function __construct(private readonly string $code, private readonly RuleCatalogDescription $description)
            {
            }

            public function code(): string
            {
                return $this->code;
            }

            public function describe(string $season): RuleCatalogDescription
            {
                return $this->description;
            }
        };
    }
}
