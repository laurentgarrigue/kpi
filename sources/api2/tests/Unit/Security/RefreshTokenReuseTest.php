<?php

namespace App\Tests\Unit\Security;

use App\Security\PresentedRefreshToken;
use App\Security\RefreshTokenReuse;
use App\Security\RefreshTokenUse;
use PHPUnit\Framework\TestCase;

/**
 * What a presented refresh token is worth ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 3ter.4},
 * AUTH-SC-10 to AUTH-SC-12): normal rotation, grace for a duplicate from the same device, closed session, replay.
 */
final class RefreshTokenReuseTest extends TestCase
{
    private const DEVICE = 'fingerprint-of-the-scoring-tablet';
    private const OTHER_DEVICE = 'fingerprint-of-another-network';

    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-11-18 10:00:00');
    }

    public function testAnActiveTokenIsRotated(): void
    {
        self::assertSame(RefreshTokenUse::Rotate, $this->decide($this->token()));
    }

    public function testAnExpiredTokenIsInvalidWhateverItsState(): void
    {
        $expired = new PresentedRefreshToken($this->ago(1), null, false, self::DEVICE);
        self::assertSame(RefreshTokenUse::Invalid, $this->decide($expired));

        $expiredAndRotated = new PresentedRefreshToken($this->ago(1), $this->ago(5), true, self::DEVICE);
        self::assertSame(RefreshTokenUse::Invalid, $this->decide($expiredAndRotated));
    }

    // AUTH-SC-10 — two tabs, or a response lost on the venue wifi
    public function testATokenRotatedSecondsAgoBySameDeviceGetsAGraceSuccessor(): void
    {
        self::assertSame(RefreshTokenUse::Grace, $this->decide($this->token(rotatedSecondsAgo: 5)));
        self::assertSame(RefreshTokenUse::Grace, $this->decide($this->token(rotatedSecondsAgo: RefreshTokenReuse::GRACE_SECONDS)));
    }

    // AUTH-SC-11 — grace never brings back a session ended by a logout or a device revocation
    public function testGraceIsRefusedWhenTheChainWasClosedMeanwhile(): void
    {
        self::assertSame(RefreshTokenUse::Closed, $this->decide($this->token(rotatedSecondsAgo: 5), chainActive: false));
    }

    // AUTH-SC-12 — replay outside the grace window
    public function testATokenRotatedLongAgoIsAReplay(): void
    {
        self::assertSame(RefreshTokenUse::Replay, $this->decide($this->token(rotatedSecondsAgo: RefreshTokenReuse::GRACE_SECONDS + 1)));
    }

    // AUTH-SC-12 — replay from another device, even within the window
    public function testADuplicateFromAnotherDeviceIsAReplay(): void
    {
        self::assertSame(RefreshTokenUse::Replay, $this->decide($this->token(rotatedSecondsAgo: 5), requestFingerprint: self::OTHER_DEVICE));
        self::assertSame(RefreshTokenUse::Replay, $this->decide($this->token(rotatedSecondsAgo: 5), requestFingerprint: null));
    }

    public function testATokenWithoutFingerprintGetsNoGrace(): void
    {
        $legacy = new PresentedRefreshToken($this->inHours(72), $this->ago(5), true, null);
        self::assertSame(RefreshTokenUse::Replay, $this->decide($legacy, requestFingerprint: null));
    }

    public function testATokenRevokedOtherwiseThanByRotationIsAReplay(): void
    {
        $loggedOut = new PresentedRefreshToken($this->inHours(72), $this->ago(5), false, self::DEVICE);
        self::assertSame(RefreshTokenUse::Replay, $this->decide($loggedOut));
    }

    private function decide(PresentedRefreshToken $token, ?string $requestFingerprint = self::DEVICE, bool $chainActive = true): RefreshTokenUse
    {
        return RefreshTokenReuse::decide($token, $requestFingerprint, $chainActive, $this->now);
    }

    private function token(?int $rotatedSecondsAgo = null): PresentedRefreshToken
    {
        return new PresentedRefreshToken(
            $this->inHours(72),
            $rotatedSecondsAgo === null ? null : $this->ago($rotatedSecondsAgo),
            $rotatedSecondsAgo !== null,
            self::DEVICE,
        );
    }

    private function ago(int $seconds): \DateTimeImmutable
    {
        return $this->now->modify("-{$seconds} seconds");
    }

    private function inHours(int $hours): \DateTimeImmutable
    {
        return $this->now->modify("+{$hours} hours");
    }
}
