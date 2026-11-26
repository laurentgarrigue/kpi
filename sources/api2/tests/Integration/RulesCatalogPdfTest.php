<?php

namespace App\Tests\Integration;

use App\MatchMedals\Catalog\MedalCatalog;
use App\MatchMedals\Catalog\MedalCatalogDescriber;
use App\MatchMedals\Catalog\MedalSurface;
use App\RuleCatalog\RuleCatalogs;
use App\Tests\Support\FakeGotenbergClient;
use Symfony\Component\HttpFoundation\Response;

/** The `rules-catalog` PDF on the fixtures (FEATURE_RULES_CATALOG_PDF.md § 5). */
final class RulesCatalogPdfTest extends ApiTestCase
{
    private const DOCUMENT = '/admin/pdf/rules-catalog?season=2998';

    protected function setUp(): void
    {
        parent::setUp();
        // The admin PDFs write the journal: rolled back at the end of each test.
        $this->beginWrites();
    }

    public function testRcat01EveryCatalogueOfTheSpecIsRegisteredInItsOrder(): void
    {
        self::assertSame(
            ['player-eligibility', 'age-categories', 'scoring', 'card-suspensions', 'medals', 'equipment', 'pdf-documents'],
            array_keys(static::getContainer()->get(RuleCatalogs::class)->all()),
        );
    }

    public function testRcat06MedalsAreDescribedWithTheThresholdsOfTheSeason(): void
    {
        $catalog = static::getContainer()->get(MedalCatalog::class);
        $catalog->update('opener', ['status' => 'retired'], 'TSUPER');
        $description = static::getContainer()->get(MedalCatalogDescriber::class)->describe('2998');

        [$thresholds, $elements] = $description->sections;
        $values = array_column($thresholds['rows'], 'value', 'name');
        foreach ($catalog->thresholdsOf('2998')->toArray() as $name => $value) {
            self::assertSame(is_array($value) ? implode(', ', $value) : (string) $value, $values[$name]);
        }
        $codes = array_column($elements['rows'], 'code');
        self::assertNotContains('opener', $codes, 'a retired element is not described');
        self::assertSame(array_column($catalog->visible(MedalSurface::Admin), 'code'), $codes);
        foreach ($elements['rows'] as $row) {
            self::assertStringNotContainsString('{', $row['explanation'], $row['code'] . ': thresholds rendered');
        }
        self::assertMatchesRegularExpression('/^règles v\d+$/', (string) $description->version);
    }

    public function testRcat08And09WholeDocumentWithItsTableOfContents(): void
    {
        $this->pdfAs('TSUPER', self::DOCUMENT, Response::HTTP_OK);
        $html = (string) static::getContainer()->get(FakeGotenbergClient::class)->html;

        self::assertStringContainsString('Règles métier', $html);
        self::assertStringContainsString('Sommaire', $html);
        foreach (['Joueur en règle', 'Catégories d&#039;âge', 'Règles de jeu du scoring', 'Cumuls de cartons et suspensions', 'Médailles et seuils', 'Contrôle équipement', 'Documents PDF'] as $title) {
            self::assertStringContainsString($title, $html);
        }
        self::assertStringContainsString('Fichiers source', $html);
    }

    public function testRcat08OneCatalogue(): void
    {
        $this->pdfAs('TSUPER', self::DOCUMENT . '&catalog=scoring', Response::HTTP_OK);
        $html = (string) static::getContainer()->get(FakeGotenbergClient::class)->html;

        self::assertStringContainsString('Règles de jeu du scoring', $html);
        self::assertStringNotContainsString('Sommaire', $html);
        self::assertStringNotContainsString('Joueur en règle', $html);
    }

    public function testRcat10UnknownCatalogue(): void
    {
        $this->pdfAs('TSUPER', self::DOCUMENT . '&catalog=unknown', Response::HTTP_BAD_REQUEST);
    }

    public function testRcat11ProfileOneOnlyAndNoPublicVersion(): void
    {
        $this->pdfAs('TADMIN', self::DOCUMENT, Response::HTTP_FORBIDDEN);
        $this->client->request('GET', '/pdf/rules-catalog?season=2998');
        self::assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
    }

    private function pdfAs(string $userCode, string $uri, int $expectedStatus): void
    {
        $this->client->request('GET', $uri, server: ['HTTP_AUTHORIZATION' => 'Bearer ' . $this->tokenFor($userCode)]);
        $response = $this->client->getResponse();
        self::assertSame($expectedStatus, $response->getStatusCode(), $uri . ' ' . substr((string) $response->getContent(), 0, 300));
    }
}
