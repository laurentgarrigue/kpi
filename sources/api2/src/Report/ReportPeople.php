<?php

namespace App\Report;

use App\Designation\Designation;
use App\Designation\DesignationRepository;
use App\Designation\EffectiveDesignations;
use App\Designation\GamedayScope;
use App\Designation\ScopeType;

/**
 * Officiels et destinataires d'un rapport, résolus depuis les Désignations (FEATURE_DESIGNATIONS.md D15, D28).
 *
 * Est officiel du périmètre : toute personne qui y a une Désignation (y compris héritée, ou sur une journée du
 * périmètre) et tout arbitre désigné d'un match du périmètre. Les profils 1 et 2 le sont partout (vérifié ailleurs).
 */
final class ReportPeople
{
    public function __construct(
        private readonly DesignationRepository $designations,
        private readonly ReportPerimeters $perimeters,
    ) {
    }

    public function isOfficial(int $matric, ReportPerimeter $perimeter): bool
    {
        $designations = array_values(array_filter(
            $this->designations->ofSeason($perimeter->season),
            static fn (Designation $d): bool => $d->matric === $matric,
        ));
        foreach ($designations as $designation) {
            if ($perimeter->scope === ReportScope::Competition && $designation->scopeType === ScopeType::Competition && $designation->scopeId === $perimeter->scopeId) {
                return true;
            }
            if ($perimeter->scope === ReportScope::Evenement && $designation->scopeType === ScopeType::Evenement && $designation->scopeId === $perimeter->scopeId) {
                return true;
            }
            foreach ($this->gamedayScopes($perimeter) as $gameday) {
                if ($gameday->covers($designation)) {
                    return true;
                }
            }
        }

        return in_array($matric, $this->perimeters->referees($perimeter->matchIds), true);
    }

    /** Délégué CNA effectif d'une journée du périmètre, ou désigné sur l'événement ou la compétition (BIL-02). */
    public function isDelegate(int $matric, ReportPerimeter $perimeter): bool
    {
        $designations = $this->designations->ofSeason($perimeter->season);
        foreach ($designations as $designation) {
            if ($designation->role === 'DELEGUE_CNA' && $designation->matric === $matric && $designation->scopeType->value === $perimeter->scope->value && $designation->scopeId === $perimeter->scopeId) {
                return true;
            }
        }
        foreach ($this->gamedayScopes($perimeter) as $gameday) {
            foreach (EffectiveDesignations::of($gameday, $designations)->holders('DELEGUE_CNA') as $holder) {
                if ($holder->matric === $matric) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Licences des destinataires (D28) : titulaires effectifs des rôles nommés sur les journées du périmètre (et RC
     * de ses compétitions), bureau CNA, auteur.
     *
     * @param list<string> $recipients spécification du type (`ROLE:<code>`, `BUREAU_CNA`, `AUTHOR`)
     *
     * @return list<int>
     */
    public function recipients(array $recipients, ReportPerimeter $perimeter, ?int $author): array
    {
        $designations = $this->designations->ofSeason($perimeter->season);
        $matrics = [];
        foreach ($recipients as $recipient) {
            if ($recipient === ReportTypeDefinition::RECIPIENT_AUTHOR) {
                if ($author !== null) {
                    $matrics[] = $author;
                }
            } elseif ($recipient === ReportTypeDefinition::RECIPIENT_BUREAU) {
                foreach ($designations as $designation) {
                    if ($designation->role === 'BUREAU_CNA') {
                        $matrics[] = $designation->matric;
                    }
                }
            } elseif (str_starts_with($recipient, 'ROLE:')) {
                $role = substr($recipient, 5);
                foreach ($this->gamedayScopes($perimeter) as $gameday) {
                    foreach (EffectiveDesignations::of($gameday, $designations)->holders($role) as $holder) {
                        $matrics[] = $holder->matric;
                    }
                }
                foreach ($designations as $designation) {
                    if ($designation->role === $role && $designation->scopeType === ScopeType::Competition && in_array($designation->scopeId, $perimeter->competitions(), true)) {
                        $matrics[] = $designation->matric;
                    }
                }
            }
        }

        return array_values(array_unique($matrics));
    }

    /** @return list<GamedayScope> */
    private function gamedayScopes(ReportPerimeter $perimeter): array
    {
        $scopes = [];
        foreach ($perimeter->gamedays as $gamedayId => $competition) {
            $scopes[] = new GamedayScope($gamedayId, $perimeter->season, $competition, $perimeter->gamedayEvents[$gamedayId] ?? []);
        }

        return $scopes;
    }
}
