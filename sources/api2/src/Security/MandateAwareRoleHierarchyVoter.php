<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Replaces Symfony's default RoleHierarchyVoter to make #[IsGranted(...)] mandate-aware.
 *
 * Why this is needed: Symfony's TokenInterface implementation caches the result of
 * getRoleNames() on first call (`$this->roleNames ??= $this->user?->getRoles()`, see
 * AbstractToken::getRoleNames()). That first call happens very early in the request
 * cycle - AccessListener evaluates the `access_control: { path: ^/admin, roles: ROLE_USER }`
 * rule on kernel.request, before ActiveMandateListener (kernel.controller) has had a
 * chance to call User::applyMandate(). Once cached, every #[IsGranted(...)] evaluated
 * later in the same request - including class- and method-level attributes on admin
 * controllers - reads that frozen, pre-mandate role set. The practical effect: a mandate
 * NEVER restricts (or elevates) what #[IsGranted(...)] allows, even though it correctly
 * drives getEffectiveNiveau() and the getAllowedXXX() filters used by manual permission
 * checks. See DOC/developer/reference/PROFILE_ROLES.md for the full writeup.
 *
 * Fix: read roles straight from the User entity (User::getRoles() is a cheap, pure
 * computation from getEffectiveNiveau(), never cached) instead of the token's cached
 * getRoleNames(). This mirrors exactly what RoleHierarchyVoter does, minus the cache.
 */
class MandateAwareRoleHierarchyVoter extends RoleHierarchyVoter
{
    public function __construct(
        private readonly RoleHierarchyInterface $roleHierarchy,
        string $prefix = 'ROLE_',
    ) {
        parent::__construct($roleHierarchy, $prefix);
    }

    protected function extractRoles(TokenInterface $token): array
    {
        $user = $token->getUser();

        // Fresh, uncached roles reflecting whatever mandate ActiveMandateListener applied
        // to the User entity earlier in this request - falls back to the token's own
        // (possibly cached) roles for any non-User token (e.g. anonymous/null tokens).
        $roles = $user instanceof User ? $user->getRoles() : $token->getRoleNames();

        return $this->roleHierarchy->getReachableRoleNames($roles);
    }
}
