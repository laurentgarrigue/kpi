<?php

namespace App\Designation;

/**
 * ============================================================================
 *  CATALOGUE DES RÔLES DE DÉSIGNATION — SEUL POINT DE CONFIGURATION
 * ============================================================================
 *
 * Chaque rôle désignable (RC, responsable d'équipe, délégué CNA, R1, juge arbitre, arbitre non-joueur, jury
 * d'appel, bureau et membres de la CNA) est défini ICI : périmètres autorisés, profil du mandat généré, exigence
 * dans le suivi de couverture, autorités, colonnes legacy recopiées. app4 reçoit ce catalogue par l'API et ne le
 * réimplémente pas (ADR-0004).
 *
 * Spec : DOC/specs/FEATURE_DESIGNATIONS.md (D3, D8, D16, D21, D23, D26, D27).
 */
final class RoleCatalog
{
    /** Seuls les profils 1 à 3 désignent, hors exception du délégué CNA (D27, DES-06, DES-07). */
    public const DESIGNATOR_MAX_PROFILE = 3;

    /** Le bureau de la CNA donne un mandat de profil 2 : seul un profil 1 ou 2 peut le désigner (pas d'élévation de droits). */
    public const BUREAU_DESIGNATOR_MAX_PROFILE = 2;

    /** Les responsables de compétition se désignent par les profils 1 et 2 ; le profil 3 les consulte seulement. */
    public const RC_DESIGNATOR_MAX_PROFILE = 2;

    private const GAMEDAY_SCOPES = [ScopeType::Evenement, ScopeType::Competition, ScopeType::Journee];

    /** @var array<string, RoleDefinition>|null */
    private static ?array $roles = null;

    /** @return array<string, RoleDefinition> */
    public static function all(): array
    {
        return self::$roles ??= self::build();
    }

    /** @return list<string> */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $code): bool
    {
        return isset(self::all()[$code]);
    }

    public static function get(string $code): RoleDefinition
    {
        return self::all()[$code] ?? throw new \InvalidArgumentException(sprintf('Rôle de Désignation inconnu : %s', $code));
    }

    /** @return array<string, RoleDefinition> */
    private static function build(): array
    {
        $required = Requirement::Required;
        $optional = Requirement::Optional;
        $none = Requirement::NotApplicable;

        $roles = [
            // Niveau : RC « national sans compétition » repris de kp_rc (ex '- CNA -', ticket designations/05)
            new RoleDefinition('RC', 'Responsable de compétition', [ScopeType::Competition, ScopeType::Niveau], 4, $required, $required,
                legacyColumns: ['Responsable_insc'], designableByMaxProfile: self::RC_DESIGNATOR_MAX_PROFILE),
            new RoleDefinition('RESP_EQUIPE', "Responsable d'équipe", [ScopeType::Equipe], 7, $required, $optional,
                mandateLimitedToTeamClub: true),
            new RoleDefinition('DELEGUE_CNA', 'Délégué CNA', self::GAMEDAY_SCOPES, 5, $required, $none,
                legacyColumns: ['Delegue'], maxPerScope: 1),
            new RoleDefinition('R1', 'R1', self::GAMEDAY_SCOPES, 6, $required, $optional,
                legacyColumns: ['Responsable_R1'], maxPerScope: 1),
            new RoleDefinition('JUGE_ARBITRE', 'Juge arbitre', self::GAMEDAY_SCOPES, 6, $required, $optional,
                legacyColumns: ['ChefArbitre'], maxPerScope: 1),
            new RoleDefinition('ARBITRE_NJ', 'Arbitre non-joueur', self::GAMEDAY_SCOPES, null, $optional, $optional,
                authorities: [Authority::Cna, Authority::Organisateur],
                legacyColumns: ['Arb_nj1', 'Arb_nj2', 'Arb_nj3', 'Arb_nj4', 'Arb_nj5'], maxPerScope: 5),
            new RoleDefinition('REP_EQUIPES', 'Représentant des équipes', self::GAMEDAY_SCOPES, null, $optional, $optional,
                legacyColumns: ['Rep_athletes'], maxPerScope: 1, designableByDelegate: true),
            new RoleDefinition('JURY_APPEL', "Membre du jury d'appel", self::GAMEDAY_SCOPES, null, $optional, $none,
                designableByDelegate: true),
            new RoleDefinition('BUREAU_CNA', 'Membre du bureau CNA', [ScopeType::Niveau], 2, $none, $none,
                noMandateUpToBaseProfile: 2, designableByMaxProfile: self::BUREAU_DESIGNATOR_MAX_PROFILE),
            new RoleDefinition('MEMBRE_CNA', 'Membre de la CNA', [ScopeType::Niveau], null, $none, $none),
        ];

        $byCode = [];
        foreach ($roles as $role) {
            $byCode[$role->code] = $role;
        }

        return $byCode;
    }
}
