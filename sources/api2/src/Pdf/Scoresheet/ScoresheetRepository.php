<?php

namespace App\Pdf\Scoresheet;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Data of the scoresheets (kp_match, kp_match_joueur, kp_match_detail), read only. */
class ScoresheetRepository
{
    /** Referees and inactive players are not on the sheet (FeuilleMatchMulti.php). */
    private const OFF_SHEET_ROLES = ['A', 'X'];

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Games with their gameday and competition, in the order of the ids given.
     *
     * @param list<int> $ids
     *
     * @return list<array<string, mixed>>
     */
    public function games(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT m.Id, m.Numero_ordre, m.Date_match, m.Heure_match, m.Heure_fin, m.Libelle, m.Terrain, m.Type,
                    m.Statut, m.Periode, m.Secretaire, m.Chronometre, m.Timeshoot, m.Ligne1, m.Ligne2,
                    m.Id_equipeA, m.Id_equipeB, m.ScoreA, m.ScoreB, m.ColorA, m.ColorB, m.Commentaires_officiels,
                    m.Arbitre_principal, m.Arbitre_secondaire, a.Libelle AS LibelleA, b.Libelle AS LibelleB,
                    j.Phase, j.Lieu, j.Departement, j.Organisateur, j.Responsable_R1, j.Responsable_insc, j.Delegue,
                    j.ChefArbitre, j.Code_competition, j.Code_saison,
                    c.Libelle AS Competition, c.Soustitre, c.Soustitre2, c.Titre_actif, c.Code_niveau
             FROM kp_match m
             JOIN kp_journee j ON j.Id = m.Id_journee
             JOIN kp_competition c ON c.Code = j.Code_competition AND c.Code_saison = j.Code_saison
             LEFT JOIN kp_competition_equipe a ON a.Id = m.Id_equipeA
             LEFT JOIN kp_competition_equipe b ON b.Id = m.Id_equipeB
             WHERE m.Id IN (?)',
            [$ids],
            [ArrayParameterType::INTEGER],
        );
        $byId = array_column($rows, null, 'Id');

        return array_values(array_filter(array_map(static fn (int $id): ?array => $byId[$id] ?? null, $ids)));
    }

    /**
     * Players of one side of a game: the game roster, else the team roster when the game has none yet (the legacy
     * sheet copied it into the game at print time; reading it is enough here).
     *
     * @return list<array<string, mixed>>
     */
    public function players(int $game, ?int $team, string $side): array
    {
        if ($team === null) {
            return [];
        }
        $players = $this->connection->fetchAllAssociative(
            "SELECT p.Numero, p.Capitaine, l.Matric, l.Nom, l.Prenom, l.Naissance, l.Origine, l.Reserve AS icf, t.Matric AS titular
             FROM kp_match_joueur p
             JOIN kp_licence l ON l.Matric = p.Matric
             LEFT JOIN kp_competition_equipe_joueur t ON t.Id_equipe = ? AND t.Matric = p.Matric
             WHERE p.Id_match = ? AND p.Equipe = ? AND p.Capitaine NOT IN (?)
             ORDER BY p.Capitaine = 'E', p.Numero, l.Nom, l.Prenom",
            [$team, $game, $side, self::OFF_SHEET_ROLES],
            [3 => ArrayParameterType::STRING],
        );
        if ($players !== []) {
            return $players;
        }

        // The team roster carries the names itself (as the presence sheet reads them).
        return $this->connection->fetchAllAssociative(
            "SELECT p.Numero, p.Capitaine, p.Matric, p.Nom, p.Prenom, l.Naissance, l.Origine, l.Reserve AS icf, p.Matric AS titular
             FROM kp_competition_equipe_joueur p
             LEFT JOIN kp_licence l ON l.Matric = p.Matric
             WHERE p.Id_equipe = ? AND p.Capitaine NOT IN (?)
             ORDER BY p.Capitaine = 'E', p.Numero, p.Nom, p.Prenom",
            [$team, self::OFF_SHEET_ROLES],
            [1 => ArrayParameterType::STRING],
        );
    }

    /**
     * Events of a game (goals, cards), timer and absences left out, in the order of the legacy sheet.
     *
     * @return list<array<string, mixed>>
     */
    public function events(int $game): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT d.Periode, d.Temps, d.Id_evt_match, d.Numero, d.Equipe_A_B, d.motif, l.Nom, l.Prenom
             FROM kp_match_detail d
             LEFT JOIN kp_licence l ON l.Matric = d.Competiteur
             WHERE d.Id_match = ? AND d.Id_evt_match NOT IN ('T', 'A')
             ORDER BY d.Periode, d.Temps DESC, d.date_insert DESC, d.Id",
            [$game],
        );
    }
}
