<?php

namespace App\Competition;

/**
 * Niveau d'une compétition déduit de son code, en un seul endroit : est **nationale** une compétition dont le code
 * commence par `N` (championnats) ou `CF` (Coupe de France), comme le legacy GestionEquipeJoueur.php.
 * Utilisé par les règles du joueur en règle (PLAYER_ELIGIBILITY_RULES.md) et les Désignations (FEATURE_DESIGNATIONS.md D26).
 */
final class CompetitionLevel
{
    public static function isNational(string $competitionCode): bool
    {
        return str_starts_with($competitionCode, 'N') || str_starts_with($competitionCode, 'CF');
    }
}
