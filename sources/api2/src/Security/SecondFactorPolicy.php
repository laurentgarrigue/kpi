<?php

namespace App\Security;

/**
 * Qui doit présenter un second facteur, et quand (AUTH_HARDENING.md § 5.2 à 5.4). Logique pure.
 *
 * Le profil maximal est le plus élevé (le plus petit numéro) entre kp_user.Niveau et les mandats du compte :
 * à ne pas confondre avec le « niveau effectif » de DROITS_PAR_PROFIL.md, qui est celui du mandat actif.
 */
final class SecondFactorPolicy
{
    /** Profils 1 à 4 : second facteur obligatoire une fois la date d'entrée en vigueur atteinte. */
    public const MAX_REQUIRED_PROFILE = 4;
    public const GRACE_DAYS = 7;

    /** @param list<int> $mandateLevels */
    public static function maxProfile(int $baseProfile, array $mandateLevels): int
    {
        return min([$baseProfile, ...$mandateLevels]);
    }

    public static function isRequiredFor(int $maxProfile): bool
    {
        return $maxProfile <= self::MAX_REQUIRED_PROFILE;
    }

    public static function decide(
        int $maxProfile,
        ?\DateTimeImmutable $enforcedFrom,
        ?string $activeMethod,
        ?\DateTimeImmutable $enrollmentRequiredSince,
        bool $trustedDevice,
        \DateTimeImmutable $now,
    ): SecondFactorOutcome {
        if ($activeMethod !== null) {
            return new SecondFactorOutcome($trustedDevice ? SecondFactorDecision::Pass : SecondFactorDecision::Challenge);
        }

        $isEnforced = $enforcedFrom !== null && $now >= $enforcedFrom;
        if (!$isEnforced || !self::isRequiredFor($maxProfile)) {
            return new SecondFactorOutcome(SecondFactorDecision::Pass);
        }

        $graceStartedAt = $enrollmentRequiredSince ?? $now;
        $enrollmentDueAt = $graceStartedAt->modify(sprintf('+%d days', self::GRACE_DAYS));
        $decision = $now >= $enrollmentDueAt ? SecondFactorDecision::EnrollmentRequired : SecondFactorDecision::Grace;

        return new SecondFactorOutcome($decision, $graceStartedAt, $enrollmentDueAt);
    }
}
