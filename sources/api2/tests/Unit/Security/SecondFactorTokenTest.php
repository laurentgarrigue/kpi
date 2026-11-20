<?php

namespace App\Tests\Unit\Security;

use App\Security\SecondFactorToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Jeton remis par /auth/login quand le second facteur est demandé ou l'enrôlement exigé
 * ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 5 — AUTH-2FA-02, AUTH-2FA-07}).
 */
final class SecondFactorTokenTest extends TestCase
{
    private const HASH = '$2y$10$abcdefghijklmnopqrstuuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0';

    private MockClock $clock;
    private SecondFactorToken $tokens;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2027-02-10 10:00:00');
        $this->tokens = new SecondFactorToken('test-secret', $this->clock);
    }

    public function testTokenIsValidForItsPurposeOnly(): void
    {
        $token = $this->tokens->create(SecondFactorToken::CHALLENGE, 'TADMIN', self::HASH);

        self::assertSame('TADMIN', $this->tokens->userCode($token));
        self::assertTrue($this->tokens->isValid($token, SecondFactorToken::CHALLENGE, self::HASH));
        self::assertFalse($this->tokens->isValid($token, SecondFactorToken::ENROLL, self::HASH));
    }

    public function testTokenDiesWhenThePasswordChanges(): void
    {
        $token = $this->tokens->create(SecondFactorToken::ENROLL, 'TADMIN', self::HASH);

        self::assertFalse($this->tokens->isValid($token, SecondFactorToken::ENROLL, self::HASH . 'x'));
    }

    public function testTokenExpires(): void
    {
        $token = $this->tokens->create(SecondFactorToken::CHALLENGE, 'TADMIN', self::HASH);
        $this->clock->sleep(SecondFactorToken::LIFETIME_SECONDS + 1);

        self::assertFalse($this->tokens->isValid($token, SecondFactorToken::CHALLENGE, self::HASH));
    }

    public function testForgedTokensAreRefused(): void
    {
        self::assertNull($this->tokens->userCode('garbage'));
        self::assertFalse($this->tokens->isValid('a.b.c.d', SecondFactorToken::CHALLENGE, self::HASH));
    }
}
