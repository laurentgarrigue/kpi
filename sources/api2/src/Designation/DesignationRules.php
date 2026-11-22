<?php

namespace App\Designation;

/**
 * Règles pures appliquées à l'écriture d'une Désignation (FEATURE_DESIGNATIONS.md DES-01, DES-05 à DES-07, D23),
 * à partir du {@see RoleCatalog}. Les contrôles qui demandent la base (licence existante, doublon) restent au service.
 */
final class DesignationRules
{
    public const ERROR_ROLE = 'role_unknown';
    public const ERROR_SCOPE = 'scope_not_allowed';
    public const ERROR_AUTHORITY = 'authority_not_allowed';
    public const ERROR_FULL = 'scope_full';

    /**
     * @param int $holdersOnScope titulaires déjà désignés pour ce rôle sur ce périmètre
     *
     * @return string|null code d'erreur, null si la Désignation est valide
     */
    public static function validate(string $role, ScopeType $scope, Authority $authority, int $holdersOnScope): ?string
    {
        if (!RoleCatalog::has($role)) {
            return self::ERROR_ROLE;
        }
        $definition = RoleCatalog::get($role);
        if (!$definition->allowsScope($scope)) {
            return self::ERROR_SCOPE;
        }
        if (!$definition->allowsAuthority($authority)) {
            return self::ERROR_AUTHORITY;
        }
        if ($definition->maxPerScope !== null && $holdersOnScope >= $definition->maxPerScope) {
            return self::ERROR_FULL;
        }

        return null;
    }

    /**
     * @param int  $profile         profil effectif de l'utilisateur (mandat actif compris)
     * @param bool $isScopeDelegate l'utilisateur est le délégué CNA effectif du périmètre visé
     */
    public static function canDesignate(string $role, int $profile, bool $isScopeDelegate): bool
    {
        $definition = RoleCatalog::get($role);

        return $profile <= $definition->designableByMaxProfile
            || ($isScopeDelegate && $definition->designableByDelegate);
    }
}
