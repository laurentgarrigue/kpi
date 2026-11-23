<?php

namespace App\Designation;

use App\Entity\User;

/**
 * Création, correction et suppression des Désignations (FEATURE_DESIGNATIONS.md D5, D7, D22, D23, D27 ;
 * DES-01 à DES-07). Les règles pures sont dans {@see DesignationRules}, les données dans {@see DesignationRepository}.
 */
final class DesignationService
{
    public function __construct(private readonly DesignationRepository $repository)
    {
    }

    /**
     * @throws DesignationRejected
     */
    public function create(
        User $user,
        string $season,
        string $role,
        ScopeType $scopeType,
        string $scopeId,
        int $matric,
        ?Authority $authority,
        int $order,
        ?string $quality,
    ): Designation {
        $authority ??= RoleCatalog::has($role) ? RoleCatalog::get($role)->defaultAuthority() : Authority::Cna;
        $error = DesignationRules::validate($role, $scopeType, $authority, 0);
        if ($error !== null) {
            throw DesignationRejected::unprocessable($error);
        }
        if (!$this->repository->scopeExists($season, $scopeType, $scopeId)) {
            throw DesignationRejected::unprocessable(DesignationRejected::SCOPE_UNKNOWN);
        }
        if (!DesignationRules::canDesignate($role, $user->getEffectiveNiveau(), $this->isScopeDelegate($user, $season, $scopeType, $scopeId))) {
            throw DesignationRejected::forbidden();
        }
        $licence = $this->repository->licence($matric);
        if ($licence === null) {
            throw DesignationRejected::unprocessable(DesignationRejected::LICENCE_UNKNOWN);
        }
        if ($this->repository->exists($season, $role, $scopeType, $scopeId, $matric)) {
            throw new DesignationRejected(DesignationRejected::DUPLICATE, 409);
        }
        $holders = $this->repository->countHolders($season, $role, $scopeType, $scopeId);
        $error = DesignationRules::validate($role, $scopeType, $authority, $holders);
        if ($error !== null) {
            throw DesignationRejected::unprocessable($error);
        }

        $designation = new Designation(0, $season, $role, $scopeType, $scopeId, $matric, $licence['club'], $authority, $order, $quality);
        $id = $this->repository->insert($designation, $user->getUserIdentifier());

        return $this->repository->find($id) ?? throw DesignationRejected::notFound();
    }

    /**
     * Correction d'une Désignation : club (profils 1–2 seulement, DES-04), autorité, ordre, qualité. Le rôle, le
     * périmètre et la personne ne changent pas : on supprime et on recrée.
     *
     * @param array{club?: string|null, authority?: Authority, order?: int, quality?: string|null} $changes
     *
     * @throws DesignationRejected
     */
    public function update(User $user, int $id, array $changes): Designation
    {
        $designation = $this->repository->find($id) ?? throw DesignationRejected::notFound();
        $columns = [];
        if (array_key_exists('club', $changes)) {
            if ($user->getEffectiveNiveau() > RoleCatalog::DESIGNATOR_MAX_PROFILE) {
                throw DesignationRejected::forbidden();
            }
            $columns['club'] = $changes['club'];
        }
        $this->assertCanDesignate($user, $designation);
        if (isset($changes['authority'])) {
            if (!RoleCatalog::get($designation->role)->allowsAuthority($changes['authority'])) {
                throw DesignationRejected::unprocessable(DesignationRules::ERROR_AUTHORITY);
            }
            $columns['autorite'] = $changes['authority']->value;
        }
        if (isset($changes['order'])) {
            $columns['ordre'] = $changes['order'];
        }
        if (array_key_exists('quality', $changes)) {
            $columns['qualite'] = $changes['quality'];
        }
        $this->repository->update($id, $columns);

        return $this->repository->find($id) ?? throw DesignationRejected::notFound();
    }

    /**
     * @throws DesignationRejected
     */
    public function delete(User $user, int $id): Designation
    {
        $designation = $this->repository->find($id) ?? throw DesignationRejected::notFound();
        $this->assertCanDesignate($user, $designation);
        $this->repository->delete($id);

        return $designation;
    }

    /**
     * L'utilisateur est-il le délégué CNA effectif du périmètre visé (D27, DES-07) ? Sur une journée, la
     * délégation peut être héritée (D9) ; sur un événement ou une compétition, elle doit y être posée.
     */
    public function isScopeDelegate(User $user, string $season, ScopeType $scopeType, string $scopeId): bool
    {
        $matric = self::matricOf($user);
        if ($matric === null) {
            return false;
        }
        if ($scopeType === ScopeType::Journee) {
            $gameday = $this->repository->gameday((int) $scopeId);
            if ($gameday === null) {
                return false;
            }
            $effective = EffectiveDesignations::of($gameday, $this->repository->ofSeason($season, 'DELEGUE_CNA'));

            return in_array($matric, array_map(static fn (Designation $d): int => $d->matric, $effective->holders('DELEGUE_CNA')), true);
        }
        if ($scopeType === ScopeType::Evenement || $scopeType === ScopeType::Competition) {
            return $this->repository->exists($season, 'DELEGUE_CNA', $scopeType, $scopeId, $matric);
        }

        return false;
    }

    /** Le code d'un compte KPI est sa licence quand il est numérique (D5). */
    public static function matricOf(User $user): ?int
    {
        $code = $user->getUserIdentifier();

        return ctype_digit($code) ? (int) $code : null;
    }

    private function assertCanDesignate(User $user, Designation $designation): void
    {
        $isDelegate = $this->isScopeDelegate($user, $designation->season, $designation->scopeType, $designation->scopeId);
        if (!DesignationRules::canDesignate($designation->role, $user->getEffectiveNiveau(), $isDelegate)) {
            throw DesignationRejected::forbidden();
        }
    }
}
