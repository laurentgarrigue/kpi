<?php

namespace App\Pdf\Games;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Games of the game documents (list, pitch grid, scoresheet), with their gameday, competition and teams. */
class GamesRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> */
    public function games(GamesQuery $query): array
    {
        $where = ['j.Code_saison = ?'];
        $parameters = [$query->season];
        $types = [];
        $in = function (string $condition, array $values, int $type) use (&$where, &$parameters, &$types): void {
            $where[] = $condition;
            $parameters[] = $values;
            $types[count($parameters) - 1] = $type;
        };

        if ($query->competitions !== []) {
            $in('j.Code_competition IN (?)', $query->competitions, ArrayParameterType::STRING);
        }
        if ($query->event !== null) {
            $where[] = 'j.Id IN (SELECT Id_journee FROM kp_evenement_journee WHERE Id_evenement = ?)';
            $parameters[] = $query->event;
        }
        if ($query->gameday !== null) {
            $where[] = 'j.Id = ?';
            $parameters[] = $query->gameday;
        }
        if ($query->games !== []) {
            $in('m.Id IN (?)', $query->games, ArrayParameterType::INTEGER);
        }
        foreach (['m.Date_match' => $query->date, 'm.Terrain' => $query->pitch, 'j.Etape' => $query->round] as $column => $value) {
            if ($value !== null) {
                $where[] = $column . ' = ?';
                $parameters[] = $value;
            }
        }
        if ($query->unlockedOnly) {
            $where[] = "(m.Validation IS NULL OR m.Validation != 'O')";
        }
        if ($query->allowedCompetitions !== null) {
            $in('j.Code_competition IN (?)', $query->allowedCompetitions ?: [''], ArrayParameterType::STRING);
        }
        if ($query->allowedGamedays !== null) {
            $in('j.Id IN (?)', $query->allowedGamedays ?: [0], ArrayParameterType::INTEGER);
        }
        if ($query->visibility !== GamesVisibility::All) {
            $where[] = "m.Publication = 'O' AND j.Publication = 'O' AND c.Statut != 'ATT'";
        }
        if ($query->visibility === GamesVisibility::Public) {
            $where[] = "c.Publication = 'O'";
        }

        return $this->connection->fetchAllAssociative(
            'SELECT m.Id, m.Id_journee, m.Numero_ordre, m.Date_match, m.Heure_match, m.Terrain, m.Libelle, m.Type,
                    m.Statut, m.Validation, m.ScoreA, m.ScoreB, m.Arbitre_principal, m.Arbitre_secondaire,
                    m.Id_equipeA, m.Id_equipeB, a.Libelle AS LibelleA, b.Libelle AS LibelleB,
                    j.Code_competition, j.Code_saison, j.Phase, j.Niveau, j.Lieu, j.Departement, j.Nom AS Journee,
                    c.Code_typeclt, c.Soustitre2
             FROM kp_match m
             JOIN kp_journee j ON j.Id = m.Id_journee
             JOIN kp_competition c ON c.Code = j.Code_competition AND c.Code_saison = j.Code_saison
             LEFT JOIN kp_competition_equipe a ON a.Id = m.Id_equipeA
             LEFT JOIN kp_competition_equipe b ON b.Id = m.Id_equipeB
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY ' . $query->sort->orderBy() . ', m.Id',
            $parameters,
            $types,
        );
    }

    /** @return array{title: string, place: string, published: bool}|null */
    public function event(int $event): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT Libelle, Lieu, Publication FROM kp_evenement WHERE Id = ?', [$event]);

        return $row === false ? null : ['title' => (string) $row['Libelle'], 'place' => (string) $row['Lieu'], 'published' => $row['Publication'] === 'O'];
    }
}
