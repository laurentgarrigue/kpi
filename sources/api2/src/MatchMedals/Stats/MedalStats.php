<?php

namespace App\MatchMedals\Stats;

use App\MatchMedals\Catalog\MedalCatalog;
use App\MatchMedals\Catalog\MedalSurface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Registry of the timeline statistics; only serves those visible on the asking surface (§ 3.1, MED-35, MED-37). */
final class MedalStats
{
    /** @var array<string, MedalStat> */
    private array $stats = [];

    /** @param iterable<MedalStat> $stats */
    public function __construct(
        #[AutowireIterator(MedalStat::TAG)] iterable $stats,
        private readonly MedalCatalog $catalog,
        private readonly Connection $connection,
    ) {
        foreach ($stats as $stat) {
            $this->stats[$stat->code()] = $stat;
        }
    }

    /** @return list<string> kinds visible on the surface, in catalogue order */
    public function visibleKinds(MedalSurface $surface): array
    {
        return array_values(array_filter($this->catalog->visibleCodes($surface, 'stat'), fn (string $c): bool => isset($this->stats[$c])));
    }

    /** Kinds worth listing for a perimeter: none while no game of it has been computed (no empty tab). */
    public function availableKinds(MedalSurface $surface, MedalScope $scope): array
    {
        $computed = $scope->competitions !== [] && (bool) $this->connection->fetchOne(
            'SELECT 1 FROM kp_match_insight i INNER JOIN kp_match m ON (m.Id = i.match_id) INNER JOIN kp_journee j ON (j.Id = m.Id_journee)
            WHERE i.is_eligible = 1 AND j.Code_saison = ? AND j.Code_competition IN (?) LIMIT 1',
            [$scope->season, $scope->competitions],
            [\PDO::PARAM_STR, \Doctrine\DBAL\ArrayParameterType::STRING],
        );

        return $computed ? $this->visibleKinds($surface) : [];
    }

    public function isVisible(string $kind, MedalSurface $surface): bool
    {
        return in_array($kind, $this->visibleKinds($surface), true);
    }

    /** @return array<string, mixed>|null null when the kind is unknown or not visible on the surface */
    public function run(string $kind, MedalScope $scope, MedalSurface $surface, int $limit): ?array
    {
        if (!$this->isVisible($kind, $surface)) {
            return null;
        }
        $stat = $this->stats[$kind];
        $thresholds = $this->catalog->thresholdsOf($scope->season);
        $data = new MedalStatsData($this->connection, $scope, $thresholds);
        $visible = [...$this->catalog->visibleCodes($surface, 'medal'), ...$this->catalog->visibleCodes($surface, 'label')];
        $result = $stat->compute(new MedalStatQuery($data, $visible, $thresholds, $limit));

        return [
            'kind' => $kind,
            'columns' => $stat->columns(),
            'rows' => $result['rows'],
            'summary' => $result['summary'] ?? (object) [],
            'games_count' => count($data->games()),
            'reliable_games_count' => count($data->games(true)),
            'needs_reliable_timeline' => $stat->needsReliableTimeline(),
        ];
    }
}
