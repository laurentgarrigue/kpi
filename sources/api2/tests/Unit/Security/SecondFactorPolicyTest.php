<?php

namespace App\Tests\Unit\Security;

use App\Security\SecondFactorDecision;
use App\Security\SecondFactorPolicy;
use PHPUnit\Framework\TestCase;

/**
 * Qui doit présenter un second facteur, et quand ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 5 —
 * AUTH-2FA-00 à 03, 11, 13}).
 */
final class SecondFactorPolicyTest extends TestCase
{
    private const NOW = '2027-02-10 10:00:00';

    public function testMaxProfileIsTheMostPrivilegedOfTheBaseProfileAndTheMandates(): void
    {
        self::assertSame(8, SecondFactorPolicy::maxProfile(8, []));
        self::assertSame(3, SecondFactorPolicy::maxProfile(3, [6]), 'mandat moins élevé : le profil de base compte');
        self::assertSame(2, SecondFactorPolicy::maxProfile(7, [9, 2]), 'mandat plus élevé : il compte');
    }

    public function testRequiredOnlyUpToProfile4(): void
    {
        self::assertTrue(SecondFactorPolicy::isRequiredFor(1));
        self::assertTrue(SecondFactorPolicy::isRequiredFor(4));
        self::assertFalse(SecondFactorPolicy::isRequiredFor(5));
        self::assertFalse(SecondFactorPolicy::isRequiredFor(10));
    }

    public function testNoObligationWithoutEnforcementDateOrBeforeIt(): void
    {
        self::assertSame(SecondFactorDecision::Pass, $this->decide(maxProfile: 2, enforcedFrom: null)->decision);
        self::assertSame(SecondFactorDecision::Pass, $this->decide(maxProfile: 2, enforcedFrom: '2027-03-01')->decision);
    }

    public function testFirstConcernedLoginOpensTheGrace(): void
    {
        $outcome = $this->decide(maxProfile: 2, enforcedFrom: '2027-02-01');

        self::assertSame(SecondFactorDecision::Grace, $outcome->decision);
        self::assertSame(self::NOW, $outcome->graceStartedAt?->format('Y-m-d H:i:s'));
        self::assertSame('2027-02-17 10:00:00', $outcome->enrollmentDueAt?->format('Y-m-d H:i:s'));
    }

    public function testGraceLastsSevenDaysThenEnrollmentIsRequired(): void
    {
        $inGrace = $this->decide(maxProfile: 4, enforcedFrom: '2027-02-01', since: '2027-02-04 10:00:01');
        $expired = $this->decide(maxProfile: 4, enforcedFrom: '2027-02-01', since: '2027-02-03 10:00:00');

        self::assertSame(SecondFactorDecision::Grace, $inGrace->decision);
        self::assertSame(SecondFactorDecision::EnrollmentRequired, $expired->decision);
    }

    public function testAccountAbove4IsNeverForced(): void
    {
        self::assertSame(SecondFactorDecision::Pass, $this->decide(maxProfile: 5, enforcedFrom: '2027-02-01', since: '2027-01-01')->decision);
    }

    public function testActiveMethodIsAlwaysChallengedUnlessTheDeviceIsTrusted(): void
    {
        self::assertSame(SecondFactorDecision::Challenge, $this->decide(maxProfile: 8, enforcedFrom: null, method: 'email')->decision);
        self::assertSame(SecondFactorDecision::Challenge, $this->decide(maxProfile: 2, enforcedFrom: '2027-02-01', method: 'totp')->decision);
        self::assertSame(SecondFactorDecision::Pass, $this->decide(maxProfile: 2, enforcedFrom: '2027-02-01', method: 'totp', trusted: true)->decision);
    }

    private function decide(int $maxProfile, ?string $enforcedFrom, ?string $method = null, ?string $since = null, bool $trusted = false): \App\Security\SecondFactorOutcome
    {
        return SecondFactorPolicy::decide(
            maxProfile: $maxProfile,
            enforcedFrom: $enforcedFrom === null ? null : new \DateTimeImmutable($enforcedFrom),
            activeMethod: $method,
            enrollmentRequiredSince: $since === null ? null : new \DateTimeImmutable($since),
            trustedDevice: $trusted,
            now: new \DateTimeImmutable(self::NOW),
        );
    }
}
