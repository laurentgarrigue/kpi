<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Socle des tests d'intégration api2 (Phase 4 du plan CI/CD).
 *
 * Ces tests bootent le kernel Symfony et tapent les endpoints en HTTP (via
 * BrowserKit, sans serveur web) : ils EXIGENT donc une base MariaDB peuplée par
 * SQL/fixtures/. Comme cette base n'existe pas partout (poste de dev sans base
 * de test, contributeur qui lance juste `composer test`), la suite se met en
 * SKIP explicite au lieu de partir en erreurs rouges : il faut `API2_TEST_DB=1`.
 *
 * ⚠️ Ces tests ne font que des SELECT. Si un jour l'un d'eux écrit, il DOIT le
 * faire dans une transaction rollbackée — jamais de mutation persistante, sinon
 * les tests deviennent dépendants de leur ordre d'exécution.
 */
abstract class ApiTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        if (getenv('API2_TEST_DB') !== '1' && ($_ENV['API2_TEST_DB'] ?? '0') !== '1') {
            self::markTestSkipped(
                'Base de test absente : API2_TEST_DB=1 requis (fixtures SQL/fixtures/, voir son README).'
            );
        }

        $this->client = static::createClient();
    }

    /**
     * Effectue un GET et renvoie la réponse JSON décodée, en vérifiant au passage
     * le code HTTP et le Content-Type. Regrouper ça ici évite de répéter les
     * trois mêmes assertions dans chaque test.
     *
     * @return array<mixed>
     */
    protected function getJson(string $uri, int $expectedStatus = 200): array
    {
        $this->client->request('GET', $uri);
        $response = $this->client->getResponse();

        self::assertSame(
            $expectedStatus,
            $response->getStatusCode(),
            sprintf('GET %s : code HTTP inattendu. Corps : %s', $uri, substr((string) $response->getContent(), 0, 500))
        );
        self::assertStringContainsString('application/json', (string) $response->headers->get('Content-Type'));

        $decoded = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded, sprintf('GET %s : la réponse JSON doit être un tableau', $uri));

        return $decoded;
    }

    /**
     * Extrait une colonne d'un tableau de lignes — pratique pour asserter sur les
     * seuls ids présents dans une réponse de liste.
     *
     * @param array<mixed> $rows
     * @return list<mixed>
     */
    protected static function column(array $rows, string $key): array
    {
        return array_values(array_column($rows, $key));
    }
}
