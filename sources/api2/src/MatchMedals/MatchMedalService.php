<?php

namespace App\MatchMedals;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Keeps the stored medals in step with the validated games (FEATURE_MATCH_MEDALS.md § 6.3). A season is computed
 * with ITS thresholds: copied from the configuration on its first computation, replaced only by computeSeason().
 */
final class MatchMedalService
{
    /** @param array<string, mixed> $thresholds */
    public function __construct(
        private readonly MatchMedalRepository $repository,
        private readonly MatchMedalCalculator $calculator,
        #[Autowire('%match_medals.thresholds%')] private readonly array $thresholds,
        #[Autowire('%match_medals.rules_version%')] private readonly int $rulesVersion,
    ) {
    }

    /** Computes a validated game, or removes the medals of a game that is not (any more). */
    public function refreshGame(int $gameId): void
    {
        $loaded = $this->repository->loadGame($gameId);
        if ($loaded === null || !$loaded['game']->isValidated) {
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
    public function computeSeason(string $season): array
    {
        $this->repository->saveSeason($season, MedalThresholds::fromArray($this->thresholds)->toArray(), $this->rulesVersion);
        $games = $this->repository->validatedGameIds($season);
        $this->refreshGames($games);
        $orphans = $this->repository->orphanGameIds($season);
        array_map(fn (int $id) => $this->repository->delete($id), $orphans);
        $this->repository->refreshSeasonCounts($season);

        return ['computed' => count($games), 'removed' => count($orphans)];
    }

    /**
     * Incremental update (cron, legacy validations): new or changed validated games, and no longer validated ones.
     *
     * @return array{computed: int, removed: int}
     */
    public function sync(): array
    {
        $stale = $this->repository->staleGameIds();
        $this->refreshGames($stale);
        $orphans = $this->repository->orphanGameIds();
        array_map(fn (int $id) => $this->repository->delete($id), $orphans);

        return ['computed' => count($stale), 'removed' => count($orphans)];
    }

    public function rulesVersion(): int
    {
        return $this->rulesVersion;
    }

    /** A season never computed first gets a copy of the current thresholds (§ 6.3, MED-32). */
    private function seasonThresholds(string $season): MedalThresholds
    {
        $copy = $this->repository->seasonThresholds($season);
        if ($copy === null) {
            $copy = MedalThresholds::fromArray($this->thresholds)->toArray();
            $this->repository->saveSeason($season, $copy, $this->rulesVersion);
        }

        return MedalThresholds::fromArray($copy);
    }
}
