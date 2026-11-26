<?php

namespace App\Discipline;

/**
 * Statut « Suspendu » d'un joueur dans la feuille de présence d'équipe (`kp_competition_equipe_joueur.Capitaine`),
 * FEATURE_CARD_SUSPENSIONS.md S16 à S18.
 */
final class SuspendedStatus
{
    public const CODE = 'S';
    /** Seuls les profils 1 et 2 donnent ou retirent ce statut (S18). */
    public const MANAGER_MAX_PROFILE = 2;
    /** Statuts qui n'entrent pas dans une feuille de match : arbitre, inactif, suspendu (S17). */
    public const OFF_MATCH_SHEET = ['A', 'X', self::CODE];
    /** Statut restauré quand on ne connaît pas celui d'avant. */
    public const DEFAULT_STATUS = '-';

    /** Le changement de statut touche-t-il « Suspendu » sans que le profil en ait le droit ? */
    public static function denies(int $profile, string $from, string $to): bool
    {
        return $from !== $to && ($from === self::CODE || $to === self::CODE) && $profile > self::MANAGER_MAX_PROFILE;
    }

    /** Liste SQL des statuts hors feuille de match, pour `Capitaine NOT IN (...)`. */
    public static function offMatchSheetSql(): string
    {
        return "'" . implode("', '", self::OFF_MATCH_SHEET) . "'";
    }
}
