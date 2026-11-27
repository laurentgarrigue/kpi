<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * « Init titulaires » : recopie la composition d'une équipe de compétition dans la feuille d'un match.
 *
 * Partagé par l'action sur les équipes (par compétition) et par celle sur les matchs (par périmètre) :
 * une seule règle de copie.
 */
class MatchStartersService
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Remplace les joueurs d'un côté du match par la composition de l'équipe.
     *
     * Seuls les joueurs actifs sont copiés (ni X inactif, ni A arbitre, comme le legacy) et dont le
     * matricule existe dans kp_licence (clé étrangère de kp_match_joueur).
     *
     * @param string $side 'A' ou 'B'
     */
    public function copyRoster(int $matchId, string $side, int $competitionTeamId): void
    {
        $this->connection->executeStatement(
            'DELETE FROM kp_match_joueur WHERE Id_match = ? AND Equipe = ?',
            [$matchId, $side]
        );
        $this->connection->executeStatement(
            "INSERT INTO kp_match_joueur (Id_match, Equipe, Matric, Numero, Capitaine)
             SELECT ?, ?, cej.Matric, cej.Numero, cej.Capitaine
             FROM kp_competition_equipe_joueur cej
             INNER JOIN kp_licence l ON l.Matric = cej.Matric
             WHERE cej.Id_equipe = ?
             AND cej.Capitaine NOT IN ('X', 'A')",
            [$matchId, $side, $competitionTeamId]
        );
    }
}
