<?php

namespace App\MatchMedals;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Keeps the stored medals in step with the validated games (FEATURE_MATCH_MEDALS.md § 6.3). A season is computed
 * with ITS thresholds: copied from the configuration on its first computation, replaced only by computeSeason().
 */
final class MatchMedalService
{
    public function __construct(
        private readonly MatchMedalRepository $repository,
        private readonly MatchMedalCalculator $calculator,
        private readonly MedalSettings $settings,
        #[Autowire('%match_medals.rules_version%')] private readonly int $rulesVersion,
        #[Autowire(service: 'services_resetter')] private readonly ResetInterface $servicesResetter,
    ) {
    }

    /** Computes a validated game, or removes the medals of a game that is not (any more). */
    public function refreshGame(int $gameId): void
    {
        $loaded = $this->repository->loadGame($gameId);
        if ($loaded === null || !$loaded['game']->isValidated || $this->settings->isDeleted($loaded['season'])) {
            $this->repository->delete($gameId);

            return;
        }
        $result = $this->calculator->compute($loaded['game'], $this->seasonThresholds($loaded['season']), $this->repository->retiredCodes());
        $this->repository->save($result, $loaded['season'], $loaded['teams'], $loaded['hash']);
        $this->repository->refreshSeasonCounts($loaded['season']);
    }

    /** @param list<int> $gameIds */
    public function refreshGames(array $gameIds): void
    {
        foreach ($gameIds as $gameId) {
            $this->refreshGame($gameId);
        }
    }

    /**
     * Full computation of one season with the CURRENT thresholds and rules version; other seasons untouched.
     *
     * @return array{computed: int, removed: int}
     */
    public function computeSeason(string $season, string $user = 'system'): array
    {
        $this->settings->unmarkDeleted($season, $user);
        $this->repository->saveSeason($season, $this->settings->thresholds(), $this->rulesVersion);
        $games = $this->repository->validatedGameIds($season);
        $this->refreshBatch($games);
        $orphans = $this->repository->orphanGameIds($season);
        array_map(fn (int $id) => $this->repository->delete($id), $orphans);
        $this->repository->refreshSeasonCounts($season);

        return ['computed' => count($games), 'removed' => count($orphans)];
    }

    /**
     * Deletes every calculation of a season: its medals, cards, referees, insights and its row. The season is
     * remembered as deleted, so that neither sync() nor a validation computes it again; computeSeason() ends that.
     *
     * @return array{removed: int}
     */
    public function deleteSeason(string $season, string $user): array
    {
        $removed = $this->repository->deleteSeason($season);
        $this->settings->markDeleted($season, $user);

        return ['removed' => $removed];
    }

    /**
     * Incremental update (cron, legacy validations): new or changed validated games, and no longer validated ones.
     *
     * @return array{computed: int, removed: int}
     */
    public function sync(): array
    {
        $stale = $this->repository->staleGameIds($this->settings->deletedSeasons());
        $this->refreshBatch($stale);
        $orphans = $this->repository->orphanGameIds();
        array_map(fn (int $id) => $this->repository->delete($id), $orphans);

        return ['computed' => count($stale), 'removed' => count($orphans)];
    }

    public function seasonIsComputed(string $season): bool
    {
        return $this->repository->seasonExists($season);
    }

    public function rulesVersion(): int
    {
        return $this->rulesVersion;
    }

    /**
     * Console batches (thousands of games): services are reset between games, as a Messenger worker does between
     * messages, otherwise in debug the profiler's query log (every query and its backtrace) exhausts the memory limit.
     *
     * @param list<int> $gameIds
     */
    private function refreshBatch(array $gameIds): void
    {
        foreach ($gameIds as $gameId) {
            $this->refreshGame($gameId);
            $this->servicesResetter->reset();
        }
    }

    /** A season never computed first gets a copy of the current thresholds (§ 6.3, MED-32). */
    private function seasonThresholds(string $season): MedalThresholds
    {
        $copy = $this->repository->seasonThresholds($season);
        if ($copy === null) {
            $copy = $this->settings->thresholds();
            $this->repository->saveSeason($season, $copy, $this->rulesVersion);
        }

        return MedalThresholds::fromArray($copy);
    }
}
