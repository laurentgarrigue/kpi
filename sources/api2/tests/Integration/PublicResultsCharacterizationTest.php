<?php

namespace App\Tests\Integration;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests de CARACTÉRISATION des endpoints de résultats consommés par app2 (API-01, API_PUBLIC_RESULTS.md § 3.1).
 *
 * Ils figent la réponse actuelle — clés, ordre, types, tri — sur les fixtures « groupe TSTRES » de
 * SQL/fixtures/data.sql, AVANT la refactorisation en service commun. Une refactorisation correcte les laisse
 * verts sans toucher aux fichiers de référence ; tout écart de format visible par app2 les fait échouer.
 *
 * Mettre à jour un fichier de référence (changement VOULU et décidé, ex. D-P2-1) :
 *   UPDATE_SNAPSHOTS=1 composer test-integration
 * puis relire le diff du fichier JSON dans la revue.
 */
final class PublicResultsCharacterizationTest extends ApiTestCase
{
    private const SNAPSHOT_DIR = __DIR__ . '/__snapshots__';

    /** @return iterable<string, array{string, string}> */
    public static function endpoints(): iterable
    {
        // Tournoi (id < 3000) : journées via kp_evenement_journee
        yield 'event games' => ['/event/77/games', 'event-77-games'];
        yield 'event charts' => ['/event/77/charts', 'event-77-charts'];
        // Journée de championnat (id >= 3000) : une seule journée
        yield 'gameday games' => ['/event/9201/games', 'event-9201-games'];
        yield 'gameday charts' => ['/event/9201/charts', 'event-9201-charts'];
        // Groupe
        yield 'group games' => ['/group/2999/TSTRES/games', 'group-TSTRES-games'];
        yield 'group charts' => ['/group/2999/TSTRES/charts', 'group-TSTRES-charts'];
    }

    #[DataProvider('endpoints')]
    public function testResponseMatchesReferenceFile(string $uri, string $snapshot): void
    {
        $actual = $this->getJson($uri);
        $file = self::SNAPSHOT_DIR . '/' . $snapshot . '.json';

        if (getenv('UPDATE_SNAPSHOTS') === '1') {
            if (!is_dir(self::SNAPSHOT_DIR)) {
                mkdir(self::SNAPSHOT_DIR, 0775, true);
            }
            file_put_contents($file, json_encode($actual, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
        }

        self::assertFileExists($file, 'fichier de référence absent : lancer une fois avec UPDATE_SNAPSHOTS=1');
        $expected = json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);

        // assertSame sur des tableaux : mêmes clés, même ORDRE, mêmes types (chaîne ≠ entier).
        self::assertSame($expected, $actual, sprintf('GET %s ne correspond plus à %s.json', $uri, $snapshot));
    }

    /** Garde-fou sur les fixtures : chaque référence doit porter des données, sinon elle ne prouve rien. */
    #[DataProvider('endpoints')]
    public function testFixturesProduceData(string $uri): void
    {
        self::assertNotEmpty($this->getJson($uri), sprintf('GET %s : réponse vide, fixtures insuffisantes', $uri));
    }
}
