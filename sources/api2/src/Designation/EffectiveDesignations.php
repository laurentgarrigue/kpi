<?php

namespace App\Designation;

/**
 * Désignations effectives d'une journée (FEATURE_DESIGNATIONS.md D9) : pour chaque rôle, on garde les Désignations
 * du périmètre le plus précis qui en a — journée, puis compétition, puis événement, puis niveau national. Ainsi un
 * tournoi (CP) désigné une fois sur la compétition ou l'événement couvre toutes ses phases, et une Désignation sur
 * une phase y fait exception.
 */
final class EffectiveDesignations
{
    /** Du plus précis au moins précis. */
    private const PRECEDENCE = [ScopeType::Journee, ScopeType::Competition, ScopeType::Evenement, ScopeType::Niveau];

    /** @param array<string, list<Designation>> $holdersByRole */
    private function __construct(private readonly array $holdersByRole)
    {
    }

    /** @param iterable<Designation> $designations Désignations candidates (toutes saisons et périmètres acceptés) */
    public static function of(GamedayScope $gameday, iterable $designations): self
    {
        /** @var array<string, array<string, list<Designation>>> $byRoleAndScope */
        $byRoleAndScope = [];
        foreach ($designations as $designation) {
            if ($gameday->covers($designation)) {
                $byRoleAndScope[$designation->role][$designation->scopeType->value][] = $designation;
            }
        }

        $holdersByRole = [];
        foreach ($byRoleAndScope as $role => $byScope) {
            foreach (self::PRECEDENCE as $scope) {
                if (isset($byScope[$scope->value])) {
                    $holdersByRole[$role] = self::sorted($byScope[$scope->value]);
                    break;
                }
            }
        }

        return new self($holdersByRole);
    }

    /** @return list<Designation> titulaires effectifs du rôle, dans l'ordre de Désignation */
    public function holders(string $role): array
    {
        return $this->holdersByRole[$role] ?? [];
    }

    /** Périmètre d'où viennent les titulaires du rôle (propre = Journee), null si aucun. */
    public function origin(string $role): ?ScopeType
    {
        return ($this->holdersByRole[$role][0] ?? null)?->scopeType;
    }

    /**
     * @param list<Designation> $designations
     *
     * @return list<Designation>
     */
    private static function sorted(array $designations): array
    {
        usort($designations, static fn (Designation $a, Designation $b): int => [$a->order, $a->id] <=> [$b->order, $b->id]);

        return $designations;
    }
}
