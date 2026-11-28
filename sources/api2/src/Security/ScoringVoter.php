<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Attribut `SCORING_ACCESS` de la console Scoring (PAGE_SCORING.md § 6.3) : profils 1 à 6 et profil 9
 * « Table de marque ». Les profils 7 et 8 héritent de `ROLE_SCORER` par la hiérarchie des rôles, d'où ce voter
 * plutôt qu'un simple rôle.
 *
 * @extends Voter<string, mixed>
 */
final class ScoringVoter extends Voter
{
    public const ACCESS = 'SCORING_ACCESS';

    private const MANAGER_MAX_LEVEL = 6;
    private const TABLE_LEVEL = 9;

    public static function allows(int $level): bool
    {
        return $level <= self::MANAGER_MAX_LEVEL || $level === self::TABLE_LEVEL;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ACCESS;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && self::allows($user->getEffectiveNiveau());
    }
}
