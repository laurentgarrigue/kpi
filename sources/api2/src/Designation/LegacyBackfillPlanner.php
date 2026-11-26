<?php

namespace App\Designation;

/**
 * Reprise des officiels saisis en texte (« NOM Prénom (licence) ») dans les colonnes legacy de kp_journee avant la
 * création des Désignations : ce qui doit devenir une Désignation, ce qui ne peut pas (pas de licence lisible).
 * Fonctions pures ; l'écriture est dans {@see LegacyBackfillService}.
 */
final class LegacyBackfillPlanner
{
    /** Le rôle RC se désigne sur la compétition (pas sur la journée) : il est repris à part. */
    private const COMPETITION_ROLE = 'RC';

    /** Licence entre parenthèses en fin de texte : « LALLIOT Philippe (17221) ». */
    public static function matricOf(?string $text): ?int
    {
        return preg_match('/\((\d+)\)\s*$/', (string) $text, $found) === 1 ? (int) $found[1] : null;
    }

    /**
     * Rôles de journée à créer d'après les colonnes legacy, pour les rôles qu'aucune Désignation effective ne couvre.
     * Un rôle dont une colonne n'est pas lisible n'est pas repris du tout : la recopie vers les colonnes legacy
     * viderait cette colonne.
     *
     * @param array<string, string|null> $columns colonne legacy => texte saisi
     */
    public static function forGameday(GamedayScope $gameday, array $columns, EffectiveDesignations $effective): LegacyBackfillPlan
    {
        $create = [];
        $unresolved = [];
        foreach (RoleCatalog::all() as $role) {
            if ($role->code === self::COMPETITION_ROLE || $role->legacyColumns === [] || !$role->allowsScope(ScopeType::Journee) || $effective->holders($role->code) !== []) {
                continue;
            }
            $found = [];
            $unreadable = [];
            foreach ($role->legacyColumns as $column) {
                $text = trim((string) ($columns[$column] ?? ''));
                if ($text === '') {
                    continue;
                }
                $matric = self::matricOf($text);
                if ($matric === null) {
                    $unreadable[] = ['role' => $role->code, 'column' => $column, 'text' => $text];
                } elseif (!in_array($matric, $found, true)) {
                    $found[] = $matric;
                }
            }
            if ($unreadable !== []) {
                array_push($unresolved, ...$unreadable);
                continue;
            }
            foreach ($found as $index => $matric) {
                $create[] = ['role' => $role->code, 'matric' => $matric, 'order' => $index + 1];
            }
        }

        return new LegacyBackfillPlan($create, $unresolved);
    }

    /**
     * RC d'une compétition d'après `Responsable_insc` de ses journées : repris seulement si toutes les journées
     * renseignées désignent la même licence.
     *
     * @param list<string|null> $texts colonne `Responsable_insc` de chaque journée de la compétition
     *
     * @return array{matric: int|null, reason: string|null} reason = pourquoi on ne reprend rien alors que du texte est saisi
     */
    public static function competitionManager(array $texts): array
    {
        $typed = array_values(array_filter(array_map(static fn (?string $text): string => trim((string) $text), $texts), static fn (string $text): bool => $text !== ''));
        if ($typed === []) {
            return ['matric' => null, 'reason' => null];
        }
        $matrics = array_unique(array_map(self::matricOf(...), $typed), SORT_REGULAR);
        if (count($matrics) > 1) {
            return ['matric' => null, 'reason' => 'les journées désignent des RC différents'];
        }
        $matric = reset($matrics);

        return $matric === null ? ['matric' => null, 'reason' => 'texte sans licence lisible'] : ['matric' => $matric, 'reason' => null];
    }
}
