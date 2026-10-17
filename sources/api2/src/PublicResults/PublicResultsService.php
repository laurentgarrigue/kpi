<?php

namespace App\PublicResults;

use App\PublicResults\Scope\ResultsScope;

/**
 * Point d'entrée unique des résultats publics (API_PUBLIC_RESULTS.md § 3) : les endpoints historiques
 * d'app2 et ceux du site public passent tous par ici, avec leur portée et leur format.
 */
final class PublicResultsService
{
    public function __construct(
        private readonly PublicResultsRepository $repository,
        private readonly ChartsBuilder $chartsBuilder,
        private readonly TeamLogos $teamLogos,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function games(ResultsScope $scope, ResultsFormat $format): array
    {
        return $this->teamLogos->fill($this->repository->findListedGames($scope, $format), 't_a_', 't_b_');
    }

    /** @return list<array<string, mixed>> */
    public function charts(ResultsScope $scope, ResultsFormat $format): array
    {
        return $this->chartsBuilder->build(
            $this->teamLogos->fill($this->repository->findChartGames($scope), 't_a_', 't_b_'),
            $this->teamLogos->fill($this->repository->findChartTeams($scope, $format), 't_'),
            $format,
            fn (string $season, string $code, string $type): array => $this->teamLogos->fill(
                $this->repository->findChartRanking($season, $code, $type),
                't_',
            ),
        );
    }
}
