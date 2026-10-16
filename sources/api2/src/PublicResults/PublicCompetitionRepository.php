<?php

namespace App\PublicResults;

use App\PublicResults\Scope\ResultsScope;
use Doctrine\DBAL\Connection;

/**
 * Requêtes des données publiques d'une compétition (en-têtes, classements, journées, événements liés) pour
 * le site public. Ne lit que le publié : compétition `Publication = 'O'`, journées publiées hors pauses.
 */
final class PublicCompetitionRepository
{
    private const HEADER_SELECT = "SELECT c.Code, c.Code_saison, c.Code_ref, g.Libelle group_libelle,
        g.Libelle_en group_libelle_en, c.Libelle, c.Soustitre, c.Soustitre2, c.Titre_actif, c.Code_typeclt,
        c.Statut, c.Code_niveau, c.BandeauLink, c.Bandeau_actif, c.LogoLink, c.Logo_actif, c.Web,
        c.Qualifies, c.Elimines, c.Code_tour,
        EXISTS (
            SELECT 1 FROM kp_match m INNER JOIN kp_journee j ON (j.Id = m.Id_journee)
            WHERE j.Code_competition = c.Code AND j.Code_saison = c.Code_saison
            AND j.Publication = 'O' AND m.Publication = 'O'
        ) has_games
        FROM kp_competition c
        LEFT JOIN kp_groupe g ON (g.Groupe = c.Code_ref)
        WHERE c.Publication = 'O'";

    /** Ordre défini puis ordre du tour : liste des compétitions, pastilles et sœurs (retour de recette 09/10/2026). */
    private const GROUP_ORDER = 'c.GroupOrder, c.Code_tour, c.Code';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function findActiveSeason(): ?string
    {
        $season = $this->connection->fetchOne("SELECT Code FROM kp_saison WHERE Etat = 'A' ORDER BY Code DESC LIMIT 1");

        return $season === false ? null : (string) $season;
    }

    /** @return list<string> saisons ayant au moins une compétition publiée, de la plus récente à la plus ancienne */
    public function findSeasons(): array
    {
        return array_map('strval', $this->connection->fetchFirstColumn(
            "SELECT s.Code FROM kp_saison s
            WHERE s.Code > '1900'
            AND EXISTS (SELECT 1 FROM kp_competition c WHERE c.Code_saison = s.Code AND c.Publication = 'O')
            ORDER BY s.Code DESC"
        ));
    }

    /** @return array<string, mixed>|null */
    public function findCompetition(string $season, string $code): ?array
    {
        $row = $this->connection->fetchAssociative(
            self::HEADER_SELECT . ' AND c.Code_saison = ? AND c.Code = ?',
            [$season, $code],
        );

        return $row === false ? null : $row;
    }

    /**
     * Compétitions publiées d'un groupe, dans l'ordre défini (GroupOrder) puis dans l'ordre du tour (Code_tour).
     *
     * @return list<array<string, mixed>>
     */
    public function findGroupCompetitions(string $season, string $groupCode): array
    {
        return $this->connection->fetchAllAssociative(
            self::HEADER_SELECT . ' AND c.Code_saison = ? AND c.Code_ref = ? ORDER BY ' . self::GROUP_ORDER,
            [$season, $groupCode],
        );
    }

    /**
     * Compétitions publiées ayant au moins une journée publiée dans l'événement.
     *
     * @return list<array<string, mixed>>
     */
    public function findEventCompetitions(int $eventId): array
    {
        return $this->connection->fetchAllAssociative(
            self::HEADER_SELECT . " AND EXISTS (
                SELECT 1 FROM kp_journee j INNER JOIN kp_evenement_journee ej ON (ej.Id_journee = j.Id)
                WHERE ej.Id_evenement = ? AND j.Code_competition = c.Code AND j.Code_saison = c.Code_saison
                AND j.Publication = 'O'
            )
            ORDER BY c.Code_niveau, c.Code_ref, c.GroupOrder, c.Code_tour, c.Code",
            [$eventId],
        );
    }

    /**
     * Pour chaque compétition d'un événement : ses journées publiées (hors pauses) dans l'événement et au total.
     *
     * @return list<array{Code: string, Code_saison: string, in_event: int|string, total: int|string}>
     */
    public function countEventGamedays(int $eventId): array
    {
        /** @var list<array{Code: string, Code_saison: string, in_event: int|string, total: int|string}> */
        return $this->connection->fetchAllAssociative(
            'SELECT c.Code, c.Code_saison,
                COUNT(DISTINCT CASE WHEN ej.Id_journee IS NOT NULL THEN j.Id END) in_event,
                COUNT(DISTINCT j.Id) total
            FROM kp_competition c
            INNER JOIN kp_journee j ON (j.Code_competition = c.Code AND j.Code_saison = c.Code_saison)
            LEFT JOIN kp_evenement_journee ej ON (ej.Id_journee = j.Id AND ej.Id_evenement = ?)
            WHERE c.Publication = \'O\' AND ' . SqlFilters::PUBLISHED_GAMEDAYS . ' AND ' . SqlFilters::NO_BREAKS . '
            AND EXISTS (
                SELECT 1 FROM kp_journee ji INNER JOIN kp_evenement_journee eji ON (eji.Id_journee = ji.Id)
                WHERE eji.Id_evenement = ? AND ji.Code_competition = c.Code AND ji.Code_saison = c.Code_saison
            )
            GROUP BY c.Code, c.Code_saison',
            [$eventId, $eventId],
        );
    }

    /** @return array<string, mixed>|null événement (tournoi) publié */
    public function findEvent(int $eventId): ?array
    {
        $row = $this->connection->fetchAssociative(
            "SELECT Id, Libelle, Lieu, logo, Date_debut, Date_fin FROM kp_evenement WHERE Id = ? AND Publication = 'O'",
            [$eventId],
        );

        return $row === false ? null : $row;
    }

    /**
     * Équipes d'une compétition avec leurs résultats publiés, triées comme le classement legacy.
     *
     * @return list<array<string, mixed>>
     */
    public function findRankedTeams(string $season, string $code, string $type): array
    {
        $orderBy = $type === 'CP' ? 'ce.CltNiveau_publi ASC, ce.Diff_publi DESC' : 'ce.Clt_publi ASC, ce.Diff_publi DESC';

        return $this->connection->fetchAllAssociative(
            "SELECT ce.Id, ce.Numero, ce.Libelle, ce.logo, ce.Clt_publi, ce.CltNiveau_publi, ce.Pts_publi,
            ce.J_publi, ce.G_publi, ce.N_publi, ce.P_publi, ce.F_publi, ce.Plus_publi, ce.Moins_publi, ce.Diff_publi
            FROM kp_competition_equipe ce
            WHERE ce.Code_saison = ? AND ce.Code_compet = ?
            ORDER BY $orderBy, ce.Libelle",
            [$season, $code],
        );
    }

    /**
     * Équipes engagées, par poule.
     *
     * @return list<array<string, mixed>>
     */
    public function findTeamsByPool(string $season, string $code): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT ce.Id, ce.Numero, ce.Libelle, ce.logo, ce.Poule
            FROM kp_competition_equipe ce
            WHERE ce.Code_saison = ? AND ce.Code_compet = ?
            ORDER BY ce.Poule, ce.Tirage, ce.Libelle",
            [$season, $code],
        );
    }

    /**
     * Journées publiées (hors pauses) d'une compétition, avec lieu et officiels.
     *
     * @return list<array<string, mixed>>
     */
    public function findGamedays(string $season, string $code): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT j.Id, j.Nom, j.Phase, j.Date_debut, j.Date_fin, j.Lieu, j.Departement, j.Organisateur,
            j.Responsable_insc, j.Responsable_R1, j.Delegue, j.ChefArbitre
            FROM kp_journee j
            INNER JOIN kp_competition c ON (j.Code_competition = c.Code AND j.Code_saison = c.Code_saison)
            WHERE j.Code_saison = ? AND j.Code_competition = ?
            AND " . SqlFilters::PUBLISHED_GAMEDAYS . ' AND ' . SqlFilters::NO_BREAKS . "
            ORDER BY j.Date_debut, j.Niveau, j.Id",
            [$season, $code],
        );
    }

    /** Nombre de journées publiées (hors pauses) d'une portée. */
    public function countGamedays(ResultsScope $scope): int
    {
        return (int) $this->connection->fetchOne(
            "SELECT COUNT(DISTINCT j.Id) FROM kp_journee j
            {$scope->join()}
            INNER JOIN kp_competition c ON (j.Code_competition = c.Code AND j.Code_saison = c.Code_saison)
            WHERE {$scope->condition()} AND " . SqlFilters::PUBLISHED_GAMEDAYS . ' AND ' . SqlFilters::NO_BREAKS,
            $scope->parameters(),
        );
    }

    /**
     * Événements publiés contenant des journées publiées (hors pauses) d'une portée, avec leur nombre.
     *
     * @return list<array<string, mixed>>
     */
    public function findLinkedEvents(ResultsScope $scope): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT e.Id, e.Libelle, e.Lieu, e.Date_debut, e.Date_fin, e.logo, COUNT(DISTINCT j.Id) gamedays
            FROM kp_journee j
            {$scope->join()}
            INNER JOIN kp_competition c ON (j.Code_competition = c.Code AND j.Code_saison = c.Code_saison)
            INNER JOIN kp_evenement_journee linked ON (linked.Id_journee = j.Id)
            INNER JOIN kp_evenement e ON (e.Id = linked.Id_evenement)
            WHERE {$scope->condition()} AND e.Publication = 'O'
            AND " . SqlFilters::PUBLISHED_GAMEDAYS . ' AND ' . SqlFilters::NO_BREAKS . "
            GROUP BY e.Id, e.Libelle, e.Lieu, e.Date_debut, e.Date_fin, e.logo",
            $scope->parameters(),
        );
    }
}
