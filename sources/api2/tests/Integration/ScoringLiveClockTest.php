<?php

namespace App\Tests\Integration;

use App\Service\ScoringLiveService;

/**
 * L'instant de départ d'un chrono lancé est enregistré à la milliseconde.
 *
 * L'incrustation recalcule le temps restant depuis `startedAtMs` (modèle 4 valeurs) : si
 * l'instant est tronqué à la seconde, elle avance de 0 à 1 s sur la console pendant que le
 * chrono tourne, puis « recule » à l'arrêt quand le temps écoulé exact arrive.
 *
 * Données : SQL/fixtures/ — match 9404 (en cours).
 */
final class ScoringLiveClockTest extends ApiTestCase
{
    private const MATCH = 9404;

    private ScoringLiveService $live;

    protected function setUp(): void
    {
        parent::setUp();
        $this->beginWrites();
        $this->live = static::getContainer()->get(ScoringLiveService::class);
    }

    private function gameClockStartedAtMs(): ?int
    {
        foreach ($this->live->getState(self::MATCH)['clocks'] as $clock) {
            if ($clock['kind'] === 'GAME') {
                return $clock['startedAtMs'];
            }
        }

        return null;
    }

    public function testARunningClockKeepsTheMillisecondsOfItsStartInstant(): void
    {
        $beforeMs = (int) floor(microtime(true) * 1000);

        $this->live->setClock(self::MATCH, [
            'kind' => 'GAME',
            'initMs' => 600_000,
            'elapsedMs' => 0,
            'running' => true,
        ]);

        $afterMs = (int) ceil(microtime(true) * 1000);
        $startedAtMs = $this->gameClockStartedAtMs();

        self::assertNotNull($startedAtMs);
        self::assertGreaterThanOrEqual($beforeMs, $startedAtMs, 'instant de départ tronqué à la seconde');
        self::assertLessThanOrEqual($afterMs, $startedAtMs);
    }
}
