<?php

namespace App\Designation;

/** Un rôle du catalogue (FEATURE_DESIGNATIONS.md § 8.2). Les valeurs sont fixées par {@see RoleCatalog}. */
final class RoleDefinition
{
    private const ENGLISH = 'en';

    /**
     * @param string          $label               libellé français, celui des compétitions nationales (DES-12)
     * @param string          $labelEn             libellé anglais, le même à tous les niveaux
     * @param string|null     $otherLabel          libellé français hors nationale, null = le même qu'en nationale
     * @param list<ScopeType> $scopes              périmètres autorisés
     * @param int|null        $mandateProfile      profil du mandat généré, null = aucun mandat (D8)
     * @param int|null        $noMandateUpToBaseProfile pas de mandat si le profil de base est déjà ≤ cette valeur (D16)
     * @param list<Authority> $authorities         autorités possibles, la première est celle par défaut (D23)
     * @param list<string>    $legacyColumns       colonnes de kp_journee recopiées depuis ce rôle (D2, ADR-0003)
     * @param int|null        $maxPerScope         titulaires au plus par périmètre, null = sans limite
     */
    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly array $scopes,
        public readonly ?int $mandateProfile,
        public readonly Requirement $nationalRequirement,
        public readonly Requirement $otherRequirement,
        public readonly array $authorities = [Authority::Cna],
        public readonly array $legacyColumns = [],
        public readonly ?int $maxPerScope = null,
        public readonly bool $mandateLimitedToTeamClub = false,
        public readonly ?int $noMandateUpToBaseProfile = null,
        public readonly bool $designableByDelegate = false,
        public readonly int $designableByMaxProfile = RoleCatalog::DESIGNATOR_MAX_PROFILE,
        public readonly string $labelEn = '',
        public readonly ?string $otherLabel = null,
    ) {
    }

    /** Libellé du rôle pour une compétition nationale ou non, en français ou en anglais (DES-12). */
    public function labelFor(bool $national, string $locale = 'fr'): string
    {
        if ($locale === self::ENGLISH) {
            return $this->labelEn;
        }

        return $national ? $this->label : ($this->otherLabel ?? $this->label);
    }

    /** Libellé français valable à tous les niveaux (« Représentant CNA / Officiel principal »), pour les exports. */
    public function labelAllLevels(): string
    {
        return $this->otherLabel === null ? $this->label : "{$this->label} / {$this->otherLabel}";
    }

    public function allowsScope(ScopeType $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function allowsAuthority(Authority $authority): bool
    {
        return in_array($authority, $this->authorities, true);
    }

    public function defaultAuthority(): Authority
    {
        return $this->authorities[0];
    }

    public function requirement(bool $national): Requirement
    {
        return $national ? $this->nationalRequirement : $this->otherRequirement;
    }

    /** Un mandat est-il généré pour un compte de ce profil de base ? (D5, D8, D16) */
    public function generatesMandateFor(int $baseProfile): bool
    {
        if ($this->mandateProfile === null) {
            return false;
        }

        return $this->noMandateUpToBaseProfile === null || $baseProfile > $this->noMandateUpToBaseProfile;
    }
}
