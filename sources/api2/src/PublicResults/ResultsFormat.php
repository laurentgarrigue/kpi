<?php

namespace App\PublicResults;

/**
 * Les deux formats historiques des réponses de matchs et de tableaux, consommés tels quels par app2 :
 * - Event : GET /event/{id}/games|charts (et, pour les tableaux, la compétition seule : plus riche) ;
 * - Group : GET /group/{season}/{code}/games|charts (et la liste de matchs d'une compétition, API-03).
 *
 * Ils ne diffèrent que par quelques colonnes, le tri, le filtre des phases Break/Pause et la construction
 * de l'arbre des tableaux ; ces différences sont regroupées ici plutôt que dupliquées dans des contrôleurs.
 */
enum ResultsFormat
{
    case Event;
    case Group;

    /** Colonnes de compétition ajoutées à la liste des matchs (juste après c_label). */
    public function listExtraColumns(): string
    {
        return match ($this) {
            self::Event => '',
            self::Group => 'c.GroupOrder c_order, c.Code_tour c_tour, c.Code_typeclt c_type,',
        };
    }

    public function listExcludesBreaks(): bool
    {
        return $this === self::Group;
    }

    public function listOrderBy(): string
    {
        return match ($this) {
            self::Event => 'm.Date_match, m.Heure_match, m.Terrain',
            self::Group => 'c.Code_tour DESC, c.GroupOrder, j.Lieu, m.Date_match DESC, m.Heure_match, m.Terrain',
        };
    }

    /** Colonnes de compétition ajoutées aux lignes d'équipes des tableaux (juste après c_order). */
    public function chartTeamsExtraColumns(): string
    {
        return match ($this) {
            self::Event => '',
            self::Group => 'c.Code_tour c_tour,',
        };
    }

    public function chartTeamsOrderBy(): string
    {
        $tour = $this === self::Group ? 'c_tour, ' : '';

        return 'c_season, ' . $tour . 'c_order, c_code, d_round, d_level DESC, d_phase, d_start DESC, '
            . 't_clt ASC, t_diff DESC, t_plus ASC';
    }
}
