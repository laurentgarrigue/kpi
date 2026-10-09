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
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function games(ResultsScope $scope, ResultsFormat $format): array
    {
        return $this->repository->findListedGames($scope, $format);
    }

    /** @return list<array<string, mixed>> */
    public function charts(ResultsScope $scope, ResultsFormat $format): array
    {
        return $this->chartsBuilder->build(
            $this->repository->findChartGames($scope),
            $this->repository->findChartTeams($scope, $format),
            $format,
            $this->repository->findChartRanking(...),
        );
    }
}
