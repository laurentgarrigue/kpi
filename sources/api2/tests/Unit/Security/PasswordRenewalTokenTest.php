<?php

namespace App\Tests\Unit\Security;

use App\Security\PasswordRenewalToken;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

/**
 * Jeton de renouvellement remis par /auth/login à un compte « à renouveler »
 * ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 2bis — AUTH-RN-02}).
 */
final class PasswordRenewalTokenTest extends TestCase
{
    private const SECRET = 'test-secret';
    private const HASH = '$2y$10$abcdefghijklmnopqrstuuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0';

    private MockClock $clock;
    private PasswordRenewalToken $tokens;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2027-01-10 10:00:00');
        $this->tokens = new PasswordRenewalToken(self::SECRET, $this->clock);
    }

    public function testTokenCarriesTheUserCodeAndIsValidForTheCurrentHash(): void
    {
        $token = $this->tokens->create('TADMIN', self::HASH);

        self::assertSame('TADMIN', $this->tokens->userCode($token));
        self::assertTrue($this->tokens->isValid($token, self::HASH));
    }

    public function testTokenExpiresAfterItsLifetime(): void
    {
        $token = $this->tokens->create('TADMIN', self::HASH);
        $this->clock->sleep(PasswordRenewalToken::LIFETIME_SECONDS + 1);

        self::assertFalse($this->tokens->isValid($token, self::HASH));
    }

    public function testTokenIsInvalidOnceThePasswordHasChanged(): void
    {
        $token = $this->tokens->create('TADMIN', self::HASH);

        self::assertFalse($this->tokens->isValid($token, password_hash('Other-Pass-42!', PASSWORD_BCRYPT)));
    }

    public function testForgedOrMalformedTokenIsRejected(): void
    {
        $token = $this->tokens->create('TADMIN', self::HASH);
        $forged = str_replace(base64_encode('TADMIN'), base64_encode('TSUPER'), $token);

        self::assertFalse($this->tokens->isValid($forged, self::HASH));
        self::assertFalse($this->tokens->isValid('garbage', self::HASH));
        self::assertNull($this->tokens->userCode('garbage'));
        self::assertFalse((new PasswordRenewalToken('other-secret', $this->clock))->isValid($token, self::HASH));
    }
}
