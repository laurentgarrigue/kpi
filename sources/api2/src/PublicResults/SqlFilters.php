<?php

namespace App\PublicResults;

/** Filtres SQL de publication communs aux requêtes publiques (alias c, j, m). */
final class SqlFilters
{
    public const PUBLISHED_GAMES = "c.Publication = 'O' AND j.Publication = 'O' AND m.Publication = 'O'";

    public const PUBLISHED_GAMEDAYS = "c.Publication = 'O' AND j.Publication = 'O'";

    /** Journées qui ne sont pas des phases de jeu. */
    public const NO_BREAKS = "j.Phase != 'Break' AND j.Phase != 'Pause'";

    /**
     * Vrai (1) quand toutes les compétitions publiées ayant une journée publiée dans l'événement appartiennent au
     * même groupe : l'événement est alors la vue de ce groupe (PAGE_EVENT_GROUP.md § 2.0).
     *
     * @param string $eventId expression SQL de l'identifiant de l'événement (ex. `e.Id`)
     */
    public static function singleGroupEvent(string $eventId): string
    {
        return "(SELECT COUNT(DISTINCT sgc.Code_ref) FROM kp_evenement_journee sgej
            INNER JOIN kp_journee sgj ON (sgj.Id = sgej.Id_journee)
            INNER JOIN kp_competition sgc ON (sgc.Code = sgj.Code_competition AND sgc.Code_saison = sgj.Code_saison)
            WHERE sgej.Id_evenement = {$eventId} AND sgc.Publication = 'O' AND sgj.Publication = 'O') = 1";
    }
}
