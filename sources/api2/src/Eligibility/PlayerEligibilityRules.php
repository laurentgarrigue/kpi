<?php

namespace App\Eligibility;

/**
 * ============================================================================
 *  PLAYER ELIGIBILITY RULES ("joueur en règle") — SINGLE CONFIGURATION POINT
 * ============================================================================
 *
 * Every rule deciding whether a player may appear as an active player on a team
 * presence sheet (kp_competition_equipe_joueur) lives in THIS file. When the
 * regulation changes, edit the constants below; the logic that applies them
 * (PlayerEligibilityChecker) and the app4 screens adapt automatically — app4
 * receives the active rule set from the API and never re-implements it.
 *
 * Full documentation (for organisers and developers):
 *   DOC/developer/reference/PLAYER_ELIGIBILITY_RULES.md
 *
 * Where the rules are enforced:
 *   - adding a player to a presence sheet        (POST  /admin/teams/{id}/players/add)
 *   - changing a player's status on the sheet    (PATCH /admin/teams/{id}/players/{matric})
 *   - every roster copy into a competition:
 *       "Copier depuis" on the presence page     (POST  /admin/teams/{id}/players/copy)
 *       Teams page "Ajouter" + roster included   (POST  /admin/competition-teams)
 *       Teams page "Dupliquer" + roster included (POST  /admin/competition-teams/duplicate)
 *       Rankings page team transfer              (POST  /admin/rankings/transfer)
 *
 * Statuses (kp_competition_equipe_joueur.Capitaine):
 *   '-' Player, 'C' Captain, 'E' Staff, 'A' Referee (non-playing), 'X' Inactive
 */
final class PlayerEligibilityRules
{
    public const LEVEL_NATIONAL = 'national';
    public const LEVEL_REGIONAL = 'regional';

    /** Non-compliant player is refused (only profiles <= FORCE_MAX_PROFILE may force, see below). */
    public const ENFORCEMENT_BLOCK = 'block';
    /** Non-compliant player is accepted, the user only gets an alert. */
    public const ENFORCEMENT_WARN = 'warn';

    // Error codes returned to app4 (translated there as `presence.error_<code>`)
    public const ERROR_LICENCE_SEASON = 'Saison_licence';
    public const ERROR_LICENCE_TYPE = 'Type_licence';
    public const ERROR_CERTIFICATE = 'Certif';
    public const ERROR_PAGAIE = 'Pagaie_couleur';
    public const ERROR_SURCLASSEMENT = 'Surclassement';

    /**
     * Criteria per competition level. A criterion set to false/null is not checked.
     *
     * - enforcement    ENFORCEMENT_BLOCK or ENFORCEMENT_WARN
     * - licenceSeason  licence renewed for the competition season (kp_licence.Origine >= season)
     * - licenceTypes   accepted kp_licence.Type_licence values (null = any type)
     * - certificateCK  competition medical certificate (kp_licence.Etat_certificat_CK = 'OUI')
     * - minPagaieECA   minimum still-water paddle colour (kp_licence.Pagaie_ECA), see PAGAIE_RANKS
     * - surclassement  age overclassing required for the categories/competitions listed below
     */
    public const RULES = [
        self::LEVEL_NATIONAL => [
            'enforcement' => self::ENFORCEMENT_BLOCK,
            'licenceSeason' => true,
            'licenceTypes' => ['Carte 1 an Compétition'],
            'certificateCK' => true,
            'minPagaieECA' => 'PAGV',
            'surclassement' => true,
        ],
        self::LEVEL_REGIONAL => [
            'enforcement' => self::ENFORCEMENT_WARN,
            'licenceSeason' => true,
            'licenceTypes' => ['Carte 1 an Compétition'],
            'certificateCK' => true,
            'minPagaieECA' => 'PAGJ',
            'surclassement' => false,
        ],
    ];

    /**
     * Paddle colours from lowest to highest. A code missing from this list (empty,
     * unknown such as 'PAGI') never satisfies a minimum.
     */
    public const PAGAIE_RANKS = [
        'PAGB' => 1,  // Blanche
        'PAGJ' => 2,  // Jaune
        'PAGV' => 3,  // Verte
        'PAGBL' => 4, // Bleue
        'PAGR' => 5,  // Rouge
        'PAGN' => 6,  // Noire
    ];

    /** Statuses counted as "playing": the only ones subject to the rules on a status change. */
    public const PLAYING_STATUSES = ['-', 'C'];

    /**
     * Profiles allowed to force a non-compliant player through a blocking rule
     * (profile 1 = webmaster, 2 = national administrator).
     */
    public const FORCE_MAX_PROFILE = 2;

    /** Statuses a non-compliant player may be forced into when ADDED to a sheet. */
    public const FORCE_ADD_STATUSES = ['E', 'A'];

    /**
     * Statuses a non-compliant player may be forced into when his status is CHANGED.
     * (Changing to a non-playing status 'E', 'A', 'X' is always allowed.)
     */
    public const FORCE_STATUS_CHANGE_STATUSES = ['-', 'C'];

    /**
     * Status given to non-compliant players holding a playing status when a roster is
     * copied into a blocking competition. Staff/referee/inactive statuses are kept.
     */
    public const COPY_INELIGIBLE_STATUS = 'X';

    /** Competitions requiring an age overclassing (kp_surclassement) outside the exempt categories. */
    public const SURCLASSEMENT_COMPETITIONS = [
        'N1D', 'N1F', 'N1H', 'N2', 'N2H', 'N3H', 'N3', 'N4H', 'N4', 'NQH', 'CFF', 'CFH', 'CFD',
    ];

    /**
     * Categories that never need an overclassing. Young categories (below JUN) AND veterans
     * from V5 upwards are NOT listed: both need an overclassing to play these competitions.
     */
    public const SURCLASSEMENT_EXEMPT_CATEGORIES = ['JUN', 'SEN', 'V1', 'V2', 'V3', 'V4'];

    /**
     * Which rule set applies to a competition (null = no eligibility control).
     *
     * National: code starting with 'N' (championships) or 'CF' (Coupe de France),
     * as in the legacy GestionEquipeJoueur.php. Regional: kp_competition.Code_niveau = 'REG'.
     */
    public static function levelFor(string $competitionCode, ?string $codeNiveau): ?string
    {
        if (str_starts_with($competitionCode, 'N') || str_starts_with($competitionCode, 'CF')) {
            return self::LEVEL_NATIONAL;
        }
        if ($codeNiveau === 'REG') {
            return self::LEVEL_REGIONAL;
        }

        return null;
    }
}
