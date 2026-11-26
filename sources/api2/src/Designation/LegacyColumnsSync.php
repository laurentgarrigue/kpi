<?php

namespace App\Designation;

/**
 * Recopie des Désignations dans les colonnes legacy de kp_journee (ticket designations/04, DES-11). Seuls les rôles
 * concernés par le changement sont réécrits : une colonne saisie à la main pour un rôle qu'aucune Désignation ne
 * touche reste telle quelle, pendant la transition (ADR-0002).
 */
final class LegacyColumnsSync
{
    public function __construct(private readonly DesignationRepository $repository)
    {
    }

    /** Après l'écriture d'une Désignation : toutes les journées de son périmètre, pour son rôle. */
    public function afterDesignationChange(Designation $designation): void
    {
        if (RoleCatalog::get($designation->role)->legacyColumns === []) {
            return;
        }
        foreach ($this->repository->gamedayIdsOf($designation->season, $designation->scopeType, $designation->scopeId) as $gamedayId) {
            $this->syncGameday($gamedayId, [$designation->role]);
        }
    }

    /**
     * Après la création d'une journée ou sa (dés)association à un événement : les rôles désignés sur la journée, sa
     * compétition, ses événements et l'événement quitté (dont les colonnes héritées doivent être vidées).
     */
    public function afterGamedayChange(int $gamedayId, ?int $leftEventId = null): void
    {
        $gameday = $this->repository->gameday($gamedayId);
        if ($gameday === null) {
            return;
        }
        $eventIds = $gameday->eventIds;
        if ($leftEventId !== null) {
            $eventIds[] = $leftEventId;
        }
        $this->syncGameday($gamedayId, $this->repository->rolesAround($gameday, $eventIds));
    }

    /** @param list<string> $roles */
    private function syncGameday(int $gamedayId, array $roles): void
    {
        $roles = array_values(array_filter($roles, static fn (string $role): bool => RoleCatalog::has($role) && RoleCatalog::get($role)->legacyColumns !== []));
        $gameday = $this->repository->gameday($gamedayId);
        if ($roles === [] || $gameday === null) {
            return;
        }
        $effective = EffectiveDesignations::of($gameday, $this->repository->ofSeason($gameday->season));
        $matrics = [];
        foreach ($roles as $role) {
            foreach ($effective->holders($role) as $holder) {
                $matrics[] = $holder->matric;
            }
        }
        $labels = $this->repository->personLabels(array_values(array_unique($matrics)));
        $this->repository->updateGamedayColumns($gamedayId, LegacyGamedayColumns::compute($effective, $roles, $labels));
    }
}
