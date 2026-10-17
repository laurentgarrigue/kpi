<?php

namespace App\PublicResults\Stats;

use App\PublicResults\CompetitionRules;
use App\PublicResults\Scope\CompetitionScope;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/** Meilleurs buteurs : buts des matchs validés et publiés (remplace kpstats.php). Aucun numéro de licence. */
final class ScorersStat implements CompetitionStat
{
    private const GOAL_EVENT = 'B';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function kind(): string
    {
        return 'scorers';
    }

    public function columns(): array
    {
        return [['key' => 'goals', 'type' => 'integer']];
    }

    public function rows(CompetitionScope $competition, int $limit): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT cej.Prenom first_name, cej.Nom last_name, cej.Numero number,
            ce.Id team_id, ce.Numero team_number, ce.Libelle team_label, COUNT(*) goals
            FROM kp_match_detail md
            INNER JOIN kp_match m ON (m.Id = md.Id_match)
            INNER JOIN kp_journee j ON (j.Id = m.Id_journee)
            INNER JOIN kp_competition_equipe ce
                ON (ce.Code_compet = j.Code_competition AND ce.Code_saison = j.Code_saison)
            INNER JOIN kp_competition_equipe_joueur cej ON (cej.Id_equipe = ce.Id AND cej.Matric = md.Competiteur)
            WHERE md.Id_evt_match = ?
            AND j.Code_competition = ? AND j.Code_saison = ?
            AND j.Publication = 'O' AND m.Publication = 'O' AND m.Validation = 'O'
            GROUP BY cej.Matric, cej.Prenom, cej.Nom, cej.Numero, ce.Id, ce.Numero, ce.Libelle
            ORDER BY goals DESC, cej.Nom, cej.Prenom
            LIMIT ?",
            [self::GOAL_EVENT, $competition->code(), $competition->season(), $limit],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
        );

        $ranks = CompetitionRules::sharedRanks(array_map(static fn (array $row): int => (int) $row['goals'], $rows));

        return array_map(static fn (array $row, int $rank): array => [
            'rank' => $rank,
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'number' => $row['number'] === null ? null : (int) $row['number'],
            'team' => ['id' => (int) $row['team_id'], 'number' => $row['team_number'] === null ? null : (int) $row['team_number'], 'label' => $row['team_label']],
            'goals' => (int) $row['goals'],
        ], $rows, $ranks);
    }
}
