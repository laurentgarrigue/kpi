<?php

namespace App\PublicSite;

use App\PublicResults\CompetitionRules;
use App\PublicResults\SqlFilters;
use Doctrine\DBAL\Connection;

/**
 * Requêtes des endpoints publics transverses (API_PUBLIC_TRANSVERSE.md) : calendrier, historique, équipes,
 * clubs, recherche. Ne lit que le publié ; ne sélectionne jamais de colonne de donnée personnelle (licence,
 * sexe, date de naissance) : la minimisation commence ici, pas au moment de sérialiser.
 */
final class PublicSiteRepository
{
    /** Compétition terminée du tour final, publiée : celles du palmarès (kphistorique.php). */
    private const FINISHED_FINALS = "c.Publication = 'O' AND c.Statut = 'END' AND c.Code_tour = " . CompetitionRules::FINAL_ROUND;

    private const COMPETITION_TITLE_COLUMNS = 'c.Code, c.Code_saison, c.Libelle, c.Soustitre, c.Soustitre2, c.Titre_actif';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function findActiveSeason(): ?string
    {
        $season = $this->connection->fetchOne("SELECT Code FROM kp_saison WHERE Etat = 'A' ORDER BY Code DESC LIMIT 1");

        return $season === false ? null : (string) $season;
    }

    // ------------------------------------------------------------------ calendrier

    /**
     * Journées publiées (hors pauses) de compétitions publiées chevauchant la période, triées pour la fusion
     * des phases d'une coupe : date, section du groupe, ordre dans le groupe, tour, nom.
     *
     * @return list<array<string, mixed>>
     */
    public function findCalendarGamedays(string $start, string $end, ?int $section, ?string $group): array
    {
        $conditions = [SqlFilters::PUBLISHED_GAMEDAYS, SqlFilters::NO_BREAKS, 'j.Date_debut <= ?', 'COALESCE(j.Date_fin, j.Date_debut) >= ?'];
        $parameters = [$end, $start];
        if ($section !== null) {
            $conditions[] = 'COALESCE(g.section, ' . PublicSiteRules::DEFAULT_SECTION . ') = ?';
            $parameters[] = $section;
        }
        if ($group !== null) {
            $conditions[] = 'c.Code_ref = ?';
            $parameters[] = $group;
        }

        return $this->connection->fetchAllAssociative(
            'SELECT j.Id, j.Code_competition, j.Code_saison, j.Nom, j.Lieu, j.Departement, j.Date_debut,
            COALESCE(j.Date_fin, j.Date_debut) Date_fin, ' . self::COMPETITION_TITLE_COLUMNS . ',
            c.Code_typeclt, c.Code_ref, COALESCE(g.section, ' . PublicSiteRules::DEFAULT_SECTION . ') section,
            e.Id event_id, e.Libelle event_libelle
            FROM kp_journee j
            INNER JOIN kp_competition c ON (j.Code_competition = c.Code AND j.Code_saison = c.Code_saison)
            LEFT JOIN kp_groupe g ON (g.Groupe = c.Code_ref)
            LEFT JOIN kp_evenement e ON (e.Id = (
                SELECT MIN(pe.Id) FROM kp_evenement_journee ej
                INNER JOIN kp_evenement pe ON (pe.Id = ej.Id_evenement)
                WHERE ej.Id_journee = j.Id AND pe.Publication = \'O\'
            ))
            WHERE ' . implode(' AND ', $conditions) . "
            ORDER BY j.Date_debut, COALESCE(g.section, " . PublicSiteRules::DEFAULT_SECTION . ") , c.GroupOrder, c.Code_tour, j.Nom, j.Id",
            $parameters,
        );
    }

    /**
     * Journées publiées (hors pauses) d'une compétition publiée, pour son abonnement ICS.
     *
     * @return list<array<string, mixed>>
     */
    public function findIcsGamedays(string $season, string $code): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT j.Id, j.Code_competition, j.Nom, j.Lieu, j.Departement, j.Date_debut, COALESCE(j.Date_fin, j.Date_debut) Date_fin,
            ' . self::COMPETITION_TITLE_COLUMNS . ', c.Code_typeclt
            FROM kp_journee j
            INNER JOIN kp_competition c ON (j.Code_competition = c.Code AND j.Code_saison = c.Code_saison)
            WHERE j.Code_saison = ? AND j.Code_competition = ? AND j.Date_debut IS NOT NULL
            AND ' . SqlFilters::PUBLISHED_GAMEDAYS . ' AND ' . SqlFilters::NO_BREAKS . '
            ORDER BY j.Date_debut, j.Niveau, j.Id',
            [$season, $code],
        );
    }

    /** @return array<string, mixed>|null journée publiée (hors pause) d'une compétition publiée */
    public function findIcsGameday(int $id): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT j.Id, j.Nom, j.Lieu, j.Departement, j.Date_debut, COALESCE(j.Date_fin, j.Date_debut) Date_fin,
            ' . self::COMPETITION_TITLE_COLUMNS . ', c.Code_typeclt
            FROM kp_journee j
            INNER JOIN kp_competition c ON (j.Code_competition = c.Code AND j.Code_saison = c.Code_saison)
            WHERE j.Id = ? AND j.Date_debut IS NOT NULL
            AND ' . SqlFilters::PUBLISHED_GAMEDAYS . ' AND ' . SqlFilters::NO_BREAKS,
            [$id],
        );

        return $row === false ? null : $row;
    }

    /**
     * Groupes ayant au moins une compétition publiée (toutes saisons), « Divers » compris : filtre du calendrier.
     *
     * @return list<array{code: string, libelle: string, libelle_en: ?string, section: int|string}>
     */
    public function findCalendarGroups(): array
    {
        /** @var list<array{code: string, libelle: string, libelle_en: ?string, section: int|string}> */
        return $this->connection->fetchAllAssociative(
            'SELECT g.Groupe code, g.Libelle libelle, g.Libelle_en libelle_en, g.section
            FROM kp_groupe g
            WHERE EXISTS (SELECT 1 FROM kp_competition c WHERE c.Code_ref = g.Groupe AND c.Publication = \'O\')
            ORDER BY g.section, g.ordre',
        );
    }

    // ------------------------------------------------------------------ historique

    /** @return list<array{code: string, libelle: string, libelle_en: ?string, section: int|string}> groupes ayant un palmarès */
    public function findHistoryGroups(): array
    {
        /** @var list<array{code: string, libelle: string, libelle_en: ?string, section: int|string}> */
        return $this->connection->fetchAllAssociative(
            'SELECT g.Groupe code, g.Libelle libelle, g.Libelle_en libelle_en, g.section
            FROM kp_groupe g
            WHERE g.section < ' . GroupSections::PUBLIC_MAX . '
            AND EXISTS (SELECT 1 FROM kp_competition c WHERE c.Code_ref = g.Groupe AND ' . self::FINISHED_FINALS . ')
            ORDER BY g.section, g.ordre',
        );
    }

    /** @return array{code: string, libelle: string, libelle_en: ?string}|null */
    public function findGroup(string $group): ?array
    {
        /** @var array{code: string, libelle: string, libelle_en: ?string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT g.Groupe code, g.Libelle libelle, g.Libelle_en libelle_en FROM kp_groupe g WHERE g.Groupe = ?',
            [$group],
        );

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> compétitions du palmarès d'un groupe, saisons décroissantes */
    public function findHistoryCompetitions(string $group): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT ' . self::COMPETITION_TITLE_COLUMNS . ', c.Code_typeclt, c.Statut, c.Code_tour
            FROM kp_competition c
            WHERE c.Code_ref = ? AND ' . self::FINISHED_FINALS . '
            ORDER BY c.Code_saison DESC, c.Code_niveau, c.GroupOrder, c.Code',
            [$group],
        );
    }

    /** @return list<array<string, mixed>> équipes des compétitions du palmarès d'un groupe */
    public function findHistoryTeams(string $group): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT ce.Code_compet, ce.Code_saison, ce.Numero, ce.Libelle, ce.logo, ce.Clt_publi, ce.CltNiveau_publi, ce.Diff_publi
            FROM kp_competition_equipe ce
            INNER JOIN kp_competition c ON (c.Code = ce.Code_compet AND c.Code_saison = ce.Code_saison)
            WHERE c.Code_ref = ? AND ' . self::FINISHED_FINALS,
            [$group],
        );
    }

    // ------------------------------------------------------------------ équipes

    /** @return list<array<string, mixed>> équipes dont le nom contient le terme, ou dont le club commence par lui */
    public function searchTeams(string $term, int $limit): array
    {
        [$contains, $dashed] = PublicSiteRules::containsPatterns($term);

        return $this->connection->fetchAllAssociative(
            'SELECT e.Numero, e.Libelle, e.Code_club, cl.Libelle club_label
            FROM kp_equipe e
            LEFT JOIN kp_club cl ON (cl.Code = e.Code_club)
            WHERE e.Libelle LIKE ? OR e.Libelle LIKE ? OR e.Code_club LIKE ?
            ORDER BY e.Libelle, e.Numero
            LIMIT ' . $limit,
            [$contains, $dashed, PublicSiteRules::prefixPattern($term)],
        );
    }

    /** @return array<string, mixed>|null */
    public function findTeam(int $number): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT e.Numero, e.Libelle, e.Code_club, cl.Libelle club_label
            FROM kp_equipe e
            LEFT JOIN kp_club cl ON (cl.Code = e.Code_club)
            WHERE e.Numero = ?',
            [$number],
        );

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> classements de l'équipe dans les compétitions publiées terminées (kpequipes.php) */
    public function findTeamHonours(int $number): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT ' . self::COMPETITION_TITLE_COLUMNS . ', c.Code_typeclt, c.Statut, c.Code_tour, c.Code_ref,
            ce.Clt_publi, ce.CltNiveau_publi
            FROM kp_competition_equipe ce
            INNER JOIN kp_competition c ON (c.Code = ce.Code_compet AND c.Code_saison = ce.Code_saison)
            INNER JOIN kp_groupe g ON (g.Groupe = c.Code_ref)
            WHERE ce.Numero = ? AND c.Publication = \'O\' AND c.Statut = \'END\'
            ORDER BY c.Code_saison DESC, g.id, c.Code_tour DESC, c.Code',
            [$number],
        );
    }

    /** @return list<array<string, mixed>> compétitions publiées où l'équipe est engagée, saisons décroissantes */
    public function findTeamCompetitions(int $number): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT DISTINCT ' . self::COMPETITION_TITLE_COLUMNS . ', c.Code_niveau, c.GroupOrder
            FROM kp_competition_equipe ce
            INNER JOIN kp_competition c ON (c.Code = ce.Code_compet AND c.Code_saison = ce.Code_saison)
            WHERE ce.Numero = ? AND c.Publication = \'O\'
            ORDER BY c.Code_saison DESC, c.Code_niveau, c.GroupOrder, c.Code',
            [$number],
        );
    }

    public function isTeamEngaged(int $number, string $season, string $code): bool
    {
        return (bool) $this->connection->fetchOne(
            'SELECT 1 FROM kp_competition_equipe ce
            INNER JOIN kp_competition c ON (c.Code = ce.Code_compet AND c.Code_saison = ce.Code_saison)
            WHERE ce.Numero = ? AND ce.Code_saison = ? AND ce.Code_compet = ? AND c.Publication = \'O\'',
            [$number, $season, $code],
        );
    }

    /**
     * Composition d'une équipe dans une compétition, avec buts et cartons des matchs validés et publiés.
     * Joueurs « A » et « X » exclus ; joueurs, puis encadrement, numéro, nom (tri de kpequipes.php).
     * Contrairement à kpequipes.php, un joueur sans aucune statistique figure aussi dans la composition.
     *
     * @return list<array<string, mixed>>
     */
    public function findRoster(int $number, string $season, string $code): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT cej.Nom, cej.Prenom, cej.Numero, cej.Categ, cej.Capitaine,
            COALESCE(SUM(md.Id_evt_match = 'B'), 0) goals,
            COALESCE(SUM(md.Id_evt_match = 'V'), 0) green,
            COALESCE(SUM(md.Id_evt_match = 'J'), 0) yellow,
            COALESCE(SUM(md.Id_evt_match = 'R'), 0) red,
            COALESCE(SUM(md.Id_evt_match = 'D'), 0) red_final
            FROM kp_competition_equipe ce
            INNER JOIN kp_competition_equipe_joueur cej ON (cej.Id_equipe = ce.Id)
            LEFT JOIN (
                kp_match_detail md
                INNER JOIN kp_match m ON (m.Id = md.Id_match AND m.Validation = 'O' AND m.Publication = 'O')
                INNER JOIN kp_journee j ON (j.Id = m.Id_journee AND j.Publication = 'O')
            ) ON (md.Competiteur = cej.Matric AND j.Code_competition = ce.Code_compet AND j.Code_saison = ce.Code_saison)
            WHERE ce.Numero = ? AND ce.Code_saison = ? AND ce.Code_compet = ?
            AND COALESCE(cej.Capitaine, '-') NOT IN ('A', 'X')
            GROUP BY cej.Id_equipe, cej.Matric, cej.Nom, cej.Prenom, cej.Numero, cej.Categ, cej.Capitaine
            ORDER BY FIELD(IF(cej.Capitaine = 'E', 'E', '-'), '-', 'E'), cej.Numero, cej.Nom, cej.Prenom",
            [$number, $season, $code],
        );
    }

    // ------------------------------------------------------------------ clubs

    /** @return list<array<string, mixed>> clubs ayant au moins une équipe, filtrés par nom ou code */
    public function findClubs(?string $term, ?int $limit = null): array
    {
        $parameters = [];
        $filter = '';
        if ($term !== null) {
            [$contains, $dashed] = PublicSiteRules::containsPatterns($term);
            $filter = ' AND (cl.Code LIKE ? OR cl.Libelle LIKE ? OR cl.Libelle LIKE ?)';
            $parameters = [PublicSiteRules::prefixPattern($term), $contains, $dashed];
        }

        return $this->connection->fetchAllAssociative(
            'SELECT cl.Code, cl.Libelle, cl.Coord, cl.Code_comite_dep, cd.Libelle department_label
            FROM kp_club cl
            LEFT JOIN kp_cd cd ON (cd.Code = cl.Code_comite_dep)
            WHERE EXISTS (SELECT 1 FROM kp_equipe e WHERE e.Code_club = cl.Code)' . $filter . '
            ORDER BY cl.Libelle, cl.Code' . ($limit === null ? '' : ' LIMIT ' . $limit),
            $parameters,
        );
    }

    /** @return array<string, mixed>|null club ayant au moins une équipe */
    public function findClub(string $code): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT cl.Code, cl.Libelle, cl.Coord, cl.Code_comite_dep, cd.Libelle department_label,
            cd.Code_comite_reg, cr.Libelle region_label, cl.www, cl.email, cl.Postal
            FROM kp_club cl
            LEFT JOIN kp_cd cd ON (cd.Code = cl.Code_comite_dep)
            LEFT JOIN kp_cr cr ON (cr.Code = cd.Code_comite_reg)
            WHERE cl.Code = ? AND EXISTS (SELECT 1 FROM kp_equipe e WHERE e.Code_club = cl.Code)',
            [$code],
        );

        return $row === false ? null : $row;
    }

    /** @return list<array{Numero: int|string, Libelle: string}> */
    public function findClubTeams(string $code): array
    {
        /** @var list<array{Numero: int|string, Libelle: string}> */
        return $this->connection->fetchAllAssociative(
            'SELECT e.Numero, e.Libelle FROM kp_equipe e WHERE e.Code_club = ? ORDER BY e.Libelle, e.Numero',
            [$code],
        );
    }

    // ------------------------------------------------------------------ recherche globale

    /** @return list<array<string, mixed>> compétitions publiées : saison active d'abord, puis les plus récentes */
    public function searchCompetitions(string $term, ?string $activeSeason, int $limit): array
    {
        [$contains, $dashed] = PublicSiteRules::containsPatterns($term);

        return $this->connection->fetchAllAssociative(
            'SELECT ' . self::COMPETITION_TITLE_COLUMNS . ', c.Code_ref
            FROM kp_competition c
            WHERE c.Publication = \'O\'
            AND (c.Libelle LIKE ? OR c.Libelle LIKE ? OR c.Soustitre LIKE ? OR c.Soustitre2 LIKE ? OR c.Code LIKE ?)
            ORDER BY c.Code_saison = ? DESC, c.Code_saison DESC, c.Code_niveau, c.GroupOrder, c.Code
            LIMIT ' . $limit,
            [$contains, $dashed, $contains, $contains, PublicSiteRules::prefixPattern($term), (string) $activeSeason],
        );
    }

    /** @return list<array<string, mixed>> événements publiés, les plus récents d'abord */
    public function searchEvents(string $term, int $limit): array
    {
        [$contains, $dashed] = PublicSiteRules::containsPatterns($term);

        return $this->connection->fetchAllAssociative(
            "SELECT e.Id, e.Libelle, e.Lieu, e.Date_debut, e.Date_fin
            FROM kp_evenement e
            WHERE e.Publication = 'O' AND (e.Libelle LIKE ? OR e.Libelle LIKE ? OR e.Lieu LIKE ?)
            ORDER BY e.Date_debut DESC, e.Id DESC
            LIMIT " . $limit,
            [$contains, $dashed, $contains],
        );
    }
}
