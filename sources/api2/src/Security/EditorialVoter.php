<?php

namespace App\Security;

use App\Cms\EditorialAccess;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Attributs des écrans éditoriaux : `CMS_EDIT` (Rédacteur ou profil ≤ 2), `CMS_ADMIN` (profil ≤ 2), utilisés avec
 * `#[IsGranted]` (FEATURE_CMS.md § 4, CMS-03).
 *
 * @extends Voter<string, mixed>
 */
final class EditorialVoter extends Voter
{
    public const EDIT = 'CMS_EDIT';
    public const ADMIN = 'CMS_ADMIN';

    public function __construct(private readonly EditorialAccess $access)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::EDIT || $attribute === self::ADMIN;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $attribute === self::ADMIN ? $this->access->canAdminister($user) : $this->access->canEdit($user);
    }
}
