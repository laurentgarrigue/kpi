<?php

namespace App\Designation;

/**
 * Valeurs des colonnes legacy de kp_journee (`Delegue`, `Responsable_R1`, `ChefArbitre`, `Rep_athletes`,
 * `Arb_nj1…5`, `Responsable_insc`) déduites des Désignations effectives (FEATURE_DESIGNATIONS.md D2, DES-11) :
 * les PDF et les pages legacy continuent de les afficher (ADR-0002, ADR-0003). Format legacy « NOM Prénom (licence) ».
 */
final class LegacyGamedayColumns
{
    /** Taille de ces colonnes dans kp_journee. */
    public const MAX_LENGTH = 80;

    /**
     * @param list<string>       $roles rôles à recopier : seuls ceux qui ont des colonnes legacy sont écrits
     * @param array<int, string> $labels libellé legacy de chaque licence ({@see personLabel()})
     *
     * @return array<string, string|null> colonne => valeur, null = vidée
     */
    public static function compute(EffectiveDesignations $effective, array $roles, array $labels): array
    {
        $columns = [];
        foreach ($roles as $role) {
            $holders = $effective->holders($role);
            foreach (RoleCatalog::get($role)->legacyColumns as $index => $column) {
                $holder = $holders[$index] ?? null;
                $columns[$column] = $holder === null ? null : mb_substr($labels[$holder->matric] ?? self::personLabel($holder->matric, null, null), 0, self::MAX_LENGTH);
            }
        }

        return $columns;
    }

    public static function personLabel(int $matric, ?string $lastName, ?string $firstName): string
    {
        return trim(sprintf('%s %s (%d)', $lastName ?? '', $firstName ?? '', $matric));
    }
}
