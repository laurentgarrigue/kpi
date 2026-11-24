<?php

namespace App\Designation;

use App\Service\NotificationService;

/**
 * Comptes et mandats générés par les Désignations (FEATURE_DESIGNATIONS.md D5, D6, D8, D10, D16 ; MAN-01 à MAN-11).
 *
 * - Compte : créé à la première Désignation d'un rôle à mandat, seulement si un email est fourni ; `Code` = licence,
 *   profil de base 8 limité aux compétitions de la Désignation ; un lien d'activation est envoyé (mécanisme du mot de passe oublié). Un compte existant n'est
 *   jamais modifié.
 * - Mandat : un par (compte, saison, rôle), recalculé depuis toutes les Désignations de ce rôle, supprimé quand il
 *   n'en reste plus. Les mandats saisis à la main (designation_role NULL) ne sont jamais touchés.
 */
final class GeneratedMandates
{
    public const ACCOUNT_EXISTING = 'existing';
    public const ACCOUNT_CREATED = 'created';
    public const ACCOUNT_NONE = 'none';

    /** Profil de base d'un compte créé pour une Désignation (D5). */
    public const NEW_ACCOUNT_PROFILE = 8;

    public function __construct(
        private readonly MandateRepository $repository,
        private readonly DesignationRepository $designations,
        private readonly NotificationService $notifications,
    ) {
    }

    /**
     * Compte du licencié désigné : existant, créé avec l'email fourni, ou aucun (MAN-01, MAN-02, MAN-11).
     */
    public function ensureAccount(Designation $designation, ?string $email): string
    {
        $matric = $designation->matric;
        $role = $designation->role;
        if ($this->repository->account($matric) !== null) {
            return self::ACCOUNT_EXISTING;
        }
        $name = $this->repository->licenceName($matric);
        if (RoleCatalog::get($role)->mandateProfile === null || $email === null || $name === null) {
            return self::ACCOUNT_NONE;
        }

        // Le compte neuf ne voit que les compétitions de cette Désignation, jamais toutes (D5)
        $perimeter = $this->perimeter($designation->season, [$designation]);
        if ($perimeter->isEmpty()) {
            return self::ACCOUNT_NONE;
        }
        $filters = $perimeter->columns();

        $identity = trim(($name['nom'] ?? '') . ' ' . ($name['prenom'] ?? ''));
        $this->repository->createAccount($matric, $identity, $email, self::NEW_ACCOUNT_PROFILE, password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT), $filters['filtre_competition'], $filters['filtre_saison']);
        $token = bin2hex(random_bytes(32));
        $this->repository->storeResetToken($matric, $token);
        $this->notifications->sendPasswordReset($email, $token, true, '', (string) $matric);

        return self::ACCOUNT_CREATED;
    }

    /** Recalcule le mandat (compte, saison, rôle) depuis les Désignations de ce rôle (D6, MAN-03 à MAN-10). */
    public function refresh(int $matric, string $season, string $role): void
    {
        $definition = RoleCatalog::get($role);
        $account = $this->repository->account($matric);
        if ($account === null || $definition->mandateProfile === null) {
            return;
        }
        $perimeter = $definition->generatesMandateFor($account['niveau'])
            ? $this->perimeter($season, $this->repository->designationsOf($matric, $season, $role))
            : null;
        if ($perimeter === null || $perimeter->isEmpty()) {
            $this->repository->deleteGeneratedMandate($matric, $season, $role);

            return;
        }
        $this->repository->saveGeneratedMandate(
            $matric,
            $season,
            $role,
            sprintf('%s %s', $definition->label, $season),
            $definition->mandateProfile,
            $perimeter->columns(),
        );
    }

    /** Une journée créée ou (dés)associée à un événement change le périmètre des mandats qui la couvrent (D10, MAN-06). */
    public function afterGamedayChange(GamedayScope $gameday, ?int $leftEventId = null): void
    {
        $eventIds = $leftEventId === null ? $gameday->eventIds : [...$gameday->eventIds, $leftEventId];
        $refreshed = [];
        foreach ($this->repository->designationsAround($gameday->season, $gameday->competitionCode, $gameday->gamedayId, $eventIds) as $designation) {
            $key = "{$designation->matric}|{$designation->role}";
            if (!isset($refreshed[$key])) {
                $refreshed[$key] = true;
                $this->refresh($designation->matric, $designation->season, $designation->role);
            }
        }
    }

    /** @param list<Designation> $designations */
    private function perimeter(string $season, array $designations): MandatePerimeter
    {
        $competitions = [];
        $gamedayIds = [];
        $teamIds = [];
        foreach ($designations as $designation) {
            match ($designation->scopeType) {
                ScopeType::Competition => $competitions[] = $designation->scopeId,
                ScopeType::Journee => $gamedayIds[] = (int) $designation->scopeId,
                ScopeType::Equipe => $teamIds[] = (int) $designation->scopeId,
                ScopeType::Evenement => array_push($gamedayIds, ...$this->designations->gamedayIdsOf($season, ScopeType::Evenement, $designation->scopeId)),
                ScopeType::Niveau => array_push($competitions, ...$this->repository->nationalCompetitions($season)),
            };
        }
        $clubs = [];
        foreach ($this->repository->teams($teamIds) as $team) {
            $competitions[] = $team['competition'];
            if ($team['club'] !== null) {
                $clubs[] = $team['club'];
            }
        }
        $gamedays = $this->repository->competitionsOfGamedays(array_values(array_unique($gamedayIds)));

        return new MandatePerimeter(
            $season,
            array_values(array_unique($competitions)),
            $gamedays,
            $gamedays === [] ? [] : $this->repository->gamedaysOfCompetitions($season, array_values(array_unique($competitions))),
            $clubs,
        );
    }
}
