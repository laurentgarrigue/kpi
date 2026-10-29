<?php

namespace App\Pdf\Presence;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Teams and players of the presence and control sheets (kp_competition_equipe, kp_competition_equipe_joueur). */
class PresenceRepository
{
    /** Captain first, players, then staff, referees and inactive players (order of the legacy sheets). */
    private const ROLE_ORDER = "FIELD(IF(j.Capitaine = 'C', '-', IF(j.Capitaine = '', '-', j.Capitaine)), '-', 'E', 'A', 'X')";

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Teams of a competition, all of them or one, restricted to some clubs when the user is limited to them.
     *
     * @param string[]|null $clubs
     *
     * @return list<array{id: int, name: string, club: string}>
     */
    public function teams(string $season, string $competition, ?int $team, ?array $clubs): array
    {
        $sql = 'SELECT Id, Libelle, Code_club FROM kp_competition_equipe WHERE Code_compet = ? AND Code_saison = ?';
        $parameters = [$competition, $season];
        $types = [];
        if ($team !== null) {
            $sql .= ' AND Id = ?';
            $parameters[] = $team;
        }
        if ($clubs !== null) {
            $sql .= ' AND Code_club IN (?)';
            $parameters[] = $clubs;
            $types[count($parameters) - 1] = ArrayParameterType::STRING;
        }

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['Id'],
            'name' => (string) $row['Libelle'],
            'club' => (string) $row['Code_club'],
        ], $this->connection->fetchAllAssociative($sql . ' ORDER BY Libelle, Id', $parameters, $types));
    }

    /** @return array{club: string, competition: string, season: string}|null */
    public function team(int $team): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT Code_club, Code_compet, Code_saison FROM kp_competition_equipe WHERE Id = ?',
            [$team],
        );

        return $row === false ? null : ['club' => (string) $row['Code_club'], 'competition' => (string) $row['Code_compet'], 'season' => (string) $row['Code_saison']];
    }

    /**
     * Players of some teams with their licence, referee and equipment control data, in the order of the sheets.
     *
     * @param list<int> $teams
     *
     * @return list<array<string, mixed>>
     */
    public function players(array $teams): array
    {
        if ($teams === []) {
            return [];
        }

        return $this->connection->fetchAllAssociative(
            'SELECT j.Id_equipe, j.Matric, j.Nom, j.Prenom, j.Sexe, j.Categ, j.Numero, j.Capitaine,
                    l.Origine, l.Numero_club, l.Naissance, l.Reserve, l.Pagaie_ECA, l.Pagaie_EVI, l.Pagaie_MER,
                    l.Etat_certificat_CK, a.arbitre, a.niveau,
                    s.kayak_status, s.vest_status, s.helmet_status, s.paddle_count
             FROM kp_competition_equipe_joueur j
             LEFT JOIN kp_licence l ON l.Matric = j.Matric
             LEFT JOIN kp_arbitre a ON a.Matric = j.Matric
             LEFT JOIN kp_scrutineering s ON s.matric = j.Matric AND s.id_equipe = j.Id_equipe
             WHERE j.Id_equipe IN (?)
             ORDER BY j.Id_equipe, ' . self::ROLE_ORDER . ', j.Numero, j.Nom, j.Prenom',
            [$teams],
            [ArrayParameterType::INTEGER],
        );
    }
}
