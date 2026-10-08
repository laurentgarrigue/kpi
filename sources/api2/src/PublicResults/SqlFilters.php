<?php

namespace App\PublicResults;

/** Filtres SQL de publication communs aux requêtes publiques (alias c, j, m). */
final class SqlFilters
{
    public const PUBLISHED_GAMES = "c.Publication = 'O' AND j.Publication = 'O' AND m.Publication = 'O'";

    public const PUBLISHED_GAMEDAYS = "c.Publication = 'O' AND j.Publication = 'O'";

    /** Journées qui ne sont pas des phases de jeu. */
    public const NO_BREAKS = "j.Phase != 'Break' AND j.Phase != 'Pause'";
}
