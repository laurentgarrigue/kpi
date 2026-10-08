<?php

namespace App\PublicResults;

use App\PublicResults\Scope\ResultsScope;
use Doctrine\DBAL\Connection;

/**
 * Requêtes SQL des résultats publics. Une requête par besoin, paramétrée par une portée (ResultsScope) et,
 * pour les endpoints historiques d'app2, par leur format (ResultsFormat). Ne renvoie que des lignes brutes :
 * la mise en forme est faite par PublicResultsService / ChartsBuilder.
 */
final class PublicResultsRepository
{
    /** Colonnes communes à toutes les lignes de match (listes et tableaux). */
    private const GAME_COLUMNS = "m.Id g_id, m.Id_journee d_id, m.Numero_ordre g_number, m.Date_match g_date,
        m.Heure_match g_time, m.Terrain g_pitch, m.Libelle g_code,
        m.Validation g_validation, m.Statut g_status, m.Periode g_period,
        m.ScoreA g_score_a, m.ScoreB g_score_b, m.CoeffA g_coef_a, m.CoeffB g_coef_b,
        m.ScoreDetailA g_score_detail_a, m.ScoreDetailB g_score_detail_b,
        m.Id_equipeA t_a_id, m.Id_equipeB t_b_id,
        cea.Libelle t_a_label, ceb.Libelle t_b_label, cea.Numero t_a_number, ceb.Numero t_b_number,
        cea.Code_club t_a_club, ceb.Code_club t_b_club,
        CASE WHEN cea.logo IS NULL THEN 'KIP/logo/empty-logo.png' ELSE cea.logo END t_a_logo,
        CASE WHEN ceb.logo IS NULL THEN 'KIP/logo/empty-logo.png' ELSE ceb.logo END t_b_logo";

    /**
     * Officiels d'un match, dans les listes de matchs seulement. Leurs numéros de licence (Matric_arbitre_*)
     * ne servent qu'à la jointure : ils ne sont jamais exposés (D-P2-1).
     */
    private const OFFICIAL_COLUMNS = "m.Arbitre_principal r_1, m.Arbitre_secondaire r_2,
        CONCAT(lcp.Nom, ' ', lcp.Prenom) r_1_name,
        CONCAT(lcs.Nom, ' ', lcs.Prenom) r_2_name,
        m.Timeshoot s_name";

    private const OFFICIAL_JOINS = "LEFT OUTER JOIN kp_licence lcp ON (m.Matric_arbitre_principal = lcp.Matric)
        LEFT OUTER JOIN kp_licence lcs ON (m.Matric_arbitre_secondaire = lcs.Matric)";

    private const TEAM_JOINS = "LEFT OUTER JOIN kp_competition_equipe cea ON (m.Id_equipeA = cea.Id)
        LEFT OUTER JOIN kp_competition_equipe ceb ON (m.Id_equipeB = ceb.Id)";

    private const PUBLISHED_GAMES = "c.Publication = 'O' AND j.Publication = 'O' AND m.Publication = 'O'";

    /** Journées qui ne sont pas des phases de jeu. */
    private const NO_BREAKS = "j.Phase != 'Break' AND j.Phase != 'Pause'";

    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * Matchs publiés d'une portée, avec leurs officiels.
     *
     * @return list<array<string, mixed>>
     */
    public function findListedGames(ResultsScope $scope, ResultsFormat $format): array
    {
        $sql = "SELECT j.Code_competition c_code, c.Code_saison c_season, j.Phase d_phase, j.Niveau d_level,
            j.Lieu d_place, j.Libelle d_label, c.Soustitre2 c_label,
            {$format->listExtraColumns()}
            " . self::GAME_COLUMNS . ",
            " . self::OFFICIAL_COLUMNS . "
            FROM kp_match m
            " . self::TEAM_JOINS . "
            " . self::OFFICIAL_JOINS . "
            INNER JOIN kp_journee j ON (m.Id_journee = j.Id)
            {$scope->join()}
            INNER JOIN kp_competition c ON (j.Code_competition = c.Code AND j.Code_saison = c.Code_saison)
            WHERE {$scope->condition()}
            AND " . self::PUBLISHED_GAMES
            . ($format->listExcludesBreaks() ? ' AND ' . self::NO_BREAKS : '') . "
            ORDER BY {$format->listOrderBy()}";

        return $this->connection->fetchAllAssociative($sql, $scope->parameters());
    }

    /**
     * Matchs publiés des compétitions commencées (statut ≠ ATT), par journée : matière des tableaux.
     *
     * @return list<array<string, mixed>>
     */
    public function findChartGames(ResultsScope $scope): array
    {
        $sql = "SELECT j.Phase d_phase, j.Niveau d_level, j.Type d_type,
            j.Lieu d_place, j.Libelle d_label, c.Soustitre2 c_label, c.Code_typeclt c_type,
            " . self::GAME_COLUMNS . "
            FROM kp_match m
            " . self::TEAM_JOINS . "
            INNER JOIN kp_journee j ON (m.Id_journee = j.Id)
            {$scope->join()}
            INNER JOIN kp_competition c ON (j.Code_competition = c.Code AND j.Code_saison = c.Code_saison)
            WHERE {$scope->condition()}
            AND c.Statut != 'ATT'
            AND " . self::PUBLISHED_GAMES
            . ($scope->isSingleGameday() ? '' : ' AND ' . self::NO_BREAKS) . "
            ORDER BY m.Id_journee, m.Date_match, m.Heure_match, m.Terrain";

        return $this->connection->fetchAllAssociative($sql, $scope->parameters());
    }

    /**
     * Journées (phases) d'une portée avec leurs équipes classées : squelette des tableaux.
     *
     * @return list<array<string, mixed>>
     */
    public function findChartTeams(ResultsScope $scope, ResultsFormat $format): array
    {
        // Compétition et journée publiées, quel que soit le format (D-P2-3).
        $filters = [$scope->condition(), "c.Publication = 'O'", "j.Publication = 'O'"];
        if (!$scope->isSingleGameday()) {
            $filters[] = self::NO_BREAKS;
        }

        $sql = "SELECT j.Code_saison c_season, j.Code_competition c_code, c.Code_typeclt c_type,
            c.GroupOrder c_order, {$format->chartTeamsExtraColumns()} c.Soustitre2 c_category, c.Statut c_status,
            j.Id d_id, j.Phase d_phase, j.Etape d_round, j.Nbequipes t_count,
            j.Niveau d_level, j.Type d_type, j.Date_debut d_start, j.Date_fin d_end,
            j.Lieu d_place, j.Departement d_dpt,
            ce.Id t_id, ce.Numero t_number, ce.Libelle t_label, ce.Code_club t_club,
            cej.Clt_publi t_clt, cej.Pts_publi t_pts, cej.J_publi t_pld, cej.G_publi t_won,
            cej.N_publi t_draw, cej.P_publi t_lost, cej.F_publi t_f, cej.Plus_publi t_plus, cej.Moins_publi t_minus,
            cej.Diff_publi t_diff, cej.PtsNiveau_publi t_ptslv, cej.CltNiveau_publi t_cltlv,
            CASE WHEN ce.logo IS NULL THEN 'KIP/logo/empty-logo.png' ELSE ce.logo END t_logo
            FROM kp_journee j
            LEFT JOIN kp_competition c ON (j.Code_saison = c.Code_saison AND j.Code_competition = c.Code)
            {$scope->join()}
            LEFT OUTER JOIN kp_competition_equipe_journee cej ON cej.Id_journee = j.Id
            LEFT OUTER JOIN kp_competition_equipe ce ON ce.Id = cej.Id
            WHERE " . implode(' AND ', $filters) . "
            ORDER BY {$format->chartTeamsOrderBy()}";

        return $this->connection->fetchAllAssociative($sql, $scope->parameters());
    }

    /**
     * Classement général publié d'une compétition, tel qu'affiché dans les tableaux d'app2.
     *
     * @return list<array<string, mixed>>
     */
    public function findChartRanking(string $season, string $code, string $type): array
    {
        if ($type === 'CHPT') {
            $sql = "SELECT ce.Id t_id, ce.Numero t_number, ce.Libelle t_label, ce.Code_club t_club,
                ce.Clt_publi t_clt, ROUND(ce.Pts_publi / 100, 0) t_pts, ce.J_publi t_pld, ce.G_publi t_won,
                ce.N_publi t_draw, ce.P_publi t_lost, ce.F_publi t_f, ce.Plus_publi t_plus, ce.Moins_publi t_minus,
                ce.Diff_publi t_diff, ce.CltNiveau_publi t_clt_cp,
                CASE WHEN ce.logo IS NULL THEN 'KIP/logo/empty-logo.png' ELSE ce.logo END t_logo
                FROM kp_competition_equipe ce
                WHERE ce.Code_compet = ?
                AND ce.Code_saison = ?
                AND Clt_publi > 0
                ORDER BY Clt_publi ASC, Diff_publi DESC";
        } else {
            $sql = "SELECT ce.Id t_id, ce.Numero t_number, ce.Libelle t_label, ce.Code_club t_club,
                ce.CltNiveau_publi t_clt_cp, ce.Poule t_group, ce.Tirage t_order,
                CASE WHEN ce.logo IS NULL THEN 'KIP/logo/empty-logo.png' ELSE ce.logo END t_logo
                FROM kp_competition_equipe ce
                WHERE ce.Code_compet = ?
                AND ce.Code_saison = ?
                ORDER BY CltNiveau_publi ASC, t_group, t_order";
        }

        return $this->connection->fetchAllAssociative($sql, [$code, $season]);
    }
}
