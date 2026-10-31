<?php

namespace App\Pdf\Ranking;

use Doctrine\DBAL\Connection;

/**
 * Rankings and games of the ranking documents (PDF_GOTENBERG.md § 7.4), from the published or the computed columns.
 * The published source only reads published gamedays and games.
 */
class RankingRepository
{
    private const STATS = ['Clt', 'CltNiveau', 'Pts', 'J', 'G', 'N', 'P', 'F', 'Plus', 'Moins', 'Diff'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Teams of a competition with their general ranking, in ranking order.
     *
     * @return list<array<string, mixed>>
     */
    public function teams(string $season, string $code, string $type, RankingSource $source): array
    {
        $rank = $source->column($type === 'CP' ? 'CltNiveau' : 'Clt');

        return $this->connection->fetchAllAssociative(
            sprintf(
                'SELECT ce.Id, ce.Numero, ce.Libelle, ce.logo, %s FROM kp_competition_equipe ce
                 WHERE ce.Code_saison = ? AND ce.Code_compet = ?
                 ORDER BY ce.%s = 0, ce.%s, ce.%s DESC, ce.Libelle',
                self::stats('ce', $source),
                $rank,
                $rank,
                $source->column('Diff'),
            ),
            [$season, $code],
        );
    }

    /**
     * Ranking of every gameday or phase, with the gameday: in the order of the phases (pools first, then by level).
     *
     * @return list<array<string, mixed>>
     */
    public function gamedayRankings(string $season, string $code, RankingSource $source): array
    {
        return $this->connection->fetchAllAssociative(
            sprintf(
                "SELECT ce.Id, ce.Libelle, j.Id AS Id_journee, j.Phase, j.Niveau, j.Lieu, j.Type, j.Date_debut, %s
                 FROM kp_competition_equipe_journee cej
                 JOIN kp_competition_equipe ce ON ce.Id = cej.Id
                 JOIN kp_journee j ON j.Id = cej.Id_journee
                 WHERE j.Code_competition = ? AND j.Code_saison = ? %s
                 ORDER BY IF(LEFT(j.Phase, 5) IN ('Group', 'Poule'), j.Phase, 'Z'), j.Niveau, j.Phase, j.Date_debut, j.Lieu,
                          cej.%s = 0, cej.%s, cej.%s DESC, cej.%s DESC",
                self::stats('cej', $source),
                $source === RankingSource::Published ? "AND j.Publication = 'O'" : '',
                $source->column('Clt'),
                $source->column('Clt'),
                $source->column('Diff'),
                $source->column('Plus'),
            ),
            [$code, $season],
        );
    }

    /**
     * Gamedays of a competition, in the order of the phases.
     *
     * @return list<array<string, mixed>>
     */
    public function gamedays(string $season, string $code, RankingSource $source): array
    {
        return $this->connection->fetchAllAssociative(
            sprintf(
                "SELECT j.Id, j.Phase, j.Niveau, j.Lieu, j.Type, j.Date_debut FROM kp_journee j
                 WHERE j.Code_competition = ? AND j.Code_saison = ? %s
                 ORDER BY IF(LEFT(j.Phase, 5) IN ('Group', 'Poule'), j.Phase, 'Z'), j.Niveau, j.Phase, j.Date_debut, j.Lieu, j.Id",
                $source === RankingSource::Published ? "AND j.Publication = 'O'" : '',
            ),
            [$code, $season],
        );
    }

    /**
     * Games of a competition, of one team or all, by date: published games only for the published source.
     *
     * @return list<array<string, mixed>>
     */
    public function games(string $season, string $code, RankingSource $source, ?int $team = null): array
    {
        $sql = "SELECT m.Id, m.Id_journee, m.Id_equipeA, m.Id_equipeB, a.Libelle AS LibelleA, b.Libelle AS LibelleB,
                       m.ScoreA, m.ScoreB, m.Validation, j.Date_debut, j.Lieu
                FROM kp_match m
                JOIN kp_journee j ON j.Id = m.Id_journee
                LEFT JOIN kp_competition_equipe a ON a.Id = m.Id_equipeA
                LEFT JOIN kp_competition_equipe b ON b.Id = m.Id_equipeB
                WHERE j.Code_competition = ? AND j.Code_saison = ?";
        $parameters = [$code, $season];
        if ($source === RankingSource::Published) {
            $sql .= " AND j.Publication = 'O' AND m.Publication = 'O'";
        }
        if ($team !== null) {
            $sql .= ' AND (m.Id_equipeA = ? OR m.Id_equipeB = ?)';
            array_push($parameters, $team, $team);
        }

        return $this->connection->fetchAllAssociative(
            $sql . ' ORDER BY j.Date_debut, j.Lieu, m.Date_match, m.Heure_match, m.Numero_ordre, m.Id',
            $parameters,
        );
    }

    /** Columns of the statistics, aliased without the suffix of the source: Clt, Pts… */
    private static function stats(string $table, RankingSource $source): string
    {
        return implode(', ', array_map(
            static fn (string $name): string => sprintf('COALESCE(%s.%s, 0) AS %s', $table, $source->column($name), $name),
            self::STATS,
        ));
    }
}
