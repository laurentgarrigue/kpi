<?php

namespace App\AgeCategory;

use App\Service\ActiveSeason;
use Doctrine\DBAL\Connection;

/**
 * Keeps the cache kp_competition_equipe_joueur.Categ equal to the age category derived from the birth date and the
 * season of the team (ADR 0001): NULL when the birth date is unknown, locked competitions included (the lock protects
 * what was entered, not this derived value). The referees pool lives in a pseudo season: its rows get the category of
 * the active season (also when a referee is added to the pool, AdminRefereesPoolController).
 */
final class CompositionAgeCategories
{
    /** Referees pool: its teams live in a pseudo season, their Categ is the one of the active season. */
    private const REFEREES_POOL_COMPETITION = 'POOL';

    public function __construct(
        private readonly Connection $connection,
        private readonly AgeCategoryRule $ageCategories,
        private readonly ActiveSeason $activeSeason,
    ) {
    }

    /** Recompute every composition of a player, all seasons (birth date edited or cleared). */
    public function refreshPlayer(int $matric): AgeCategoryChanges
    {
        return $this->applyAndReturn($this->plan('cej.Matric = ?', [$matric]));
    }

    /** Recompute every player of a team (player added, roster copied or imported). */
    public function refreshTeam(int $teamId): AgeCategoryChanges
    {
        return $this->applyAndReturn($this->plan('cej.Id_equipe = ?', [$teamId]));
    }

    /** Every composition whose cached Categ is wrong, without writing anything (migration dry run). */
    public function planAll(): AgeCategoryChanges
    {
        return $this->plan('1 = 1', []);
    }

    /** Write planned changes. */
    public function apply(AgeCategoryChanges $changes): void
    {
        $this->connection->transactional(function () use ($changes): void {
            foreach ($changes->all() as $change) {
                $this->connection->update(
                    'kp_competition_equipe_joueur',
                    ['Categ' => $change->after],
                    ['Id_equipe' => $change->teamId, 'Matric' => $change->matric]
                );
            }
        });
    }

    /** Write planned changes and return them (the report of a refresh). */
    private function applyAndReturn(AgeCategoryChanges $changes): AgeCategoryChanges
    {
        $this->apply($changes);

        return $changes;
    }

    /** @param list<int> $params */
    private function plan(string $scope, array $params): AgeCategoryChanges
    {
        $rows = $this->connection->iterateAssociative(
            "SELECT cej.Id_equipe, cej.Matric, cej.Categ, ce.Code_compet, ce.Code_saison, l.Naissance
             FROM kp_competition_equipe_joueur cej
             INNER JOIN kp_competition_equipe ce ON ce.Id = cej.Id_equipe
             LEFT JOIN kp_licence l ON l.Matric = cej.Matric
             WHERE $scope",
            $params
        );

        $activeSeason = $this->activeSeason->code();
        $changes = [];
        foreach ($rows as $row) {
            $season = (string) $row['Code_saison'];
            $categorySeason = $row['Code_compet'] === self::REFEREES_POOL_COMPETITION ? $activeSeason : $season;
            $after = $this->ageCategories->categoryFor($row['Naissance'], $categorySeason)?->code;
            if ($row['Categ'] !== $after) {
                $changes[] = new AgeCategoryChange((int) $row['Id_equipe'], (int) $row['Matric'], $season, $row['Categ'], $after);
            }
        }

        return new AgeCategoryChanges($changes);
    }
}
