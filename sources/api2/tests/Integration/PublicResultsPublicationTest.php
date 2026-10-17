<?php

namespace App\Tests\Integration;

/**
 * Données publiques seulement (API_PUBLIC_RESULTS.md API-04, API-14, décisions D-P2-1 et D-P2-3) :
 * aucun numéro de licence, aucune journée ni compétition non publiée, quel que soit l'endpoint de résultats.
 */
final class PublicResultsPublicationTest extends ApiTestCase
{
    private const GAME_LISTS = ['/event/77/games', '/event/9201/games', '/group/2999/TSTRES/games'];

    /** API-14 / D-P2-1 : les numéros de licence des arbitres ne sortent plus (leurs noms, si). */
    public function testGameListsExposeNoRefereeLicenceNumber(): void
    {
        foreach (self::GAME_LISTS as $uri) {
            foreach ($this->getJson($uri) as $game) {
                self::assertArrayNotHasKey('r_1_id', $game, $uri);
                self::assertArrayNotHasKey('r_2_id', $game, $uri);
                self::assertArrayHasKey('r_1', $game, $uri);
                self::assertArrayHasKey('r_1_name', $game, $uri);
            }
        }
    }

    /** API-04 / D-P2-3 : la journée non publiée 9203 de RCH n'apparaît pas comme phase des tableaux. */
    public function testChartsListOnlyPublishedGamedays(): void
    {
        foreach (['/group/2999/TSTRES/charts', '/event/77/charts'] as $uri) {
            $gamedayIds = [];
            foreach ($this->getJson($uri) as $chart) {
                self::assertNotSame('RNP', $chart['code'], $uri . ' : compétition non publiée');
                foreach ($chart['rounds'] as $round) {
                    foreach ($round['phases'] as $phase) {
                        foreach ($phase['teams'] ?? [] as $team) {
                            $gamedayIds[] = $team['d_id'] ?? null;
                        }
                        self::assertNotSame('Journée 3', $phase['libelle'], $uri . ' : journée non publiée');
                    }
                }
            }
            self::assertNotContains(9203, $gamedayIds, $uri);
        }
    }
}
