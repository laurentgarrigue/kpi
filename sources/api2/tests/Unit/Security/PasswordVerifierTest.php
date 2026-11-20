<?php

namespace App\Tests\Unit\Security;

use App\Security\PasswordMatch;
use App\Security\PasswordVerifier;
use PHPUnit\Framework\TestCase;

/**
 * Vérification d'un mot de passe contre le hash stocké dans kp_user.Pwd et retrait du MD5
 * ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 2bis — AUTH-RN-07, AUTH-RN-08}).
 */
final class PasswordVerifierTest extends TestCase
{
    private const PASSWORD = 'Correct-Horse-99!';

    public function testBcryptHashMatchesItsPassword(): void
    {
        $verifier = new PasswordVerifier(legacyMd5Fallback: false);

        self::assertSame(PasswordMatch::Bcrypt, $verifier->verify(self::PASSWORD, password_hash(self::PASSWORD, PASSWORD_BCRYPT)));
    }

    public function testWrongPasswordDoesNotMatch(): void
    {
        $verifier = new PasswordVerifier(legacyMd5Fallback: true);

        self::assertSame(PasswordMatch::None, $verifier->verify('wrong', password_hash(self::PASSWORD, PASSWORD_BCRYPT)));
        self::assertSame(PasswordMatch::None, $verifier->verify('wrong', md5(self::PASSWORD)));
    }

    public function testMd5HashMatchesWhileTheFallbackIsEnabled(): void
    {
        $verifier = new PasswordVerifier(legacyMd5Fallback: true);

        self::assertSame(PasswordMatch::LegacyMd5, $verifier->verify(self::PASSWORD, md5(self::PASSWORD)));
    }

    public function testMd5HashIsRefusedOnceTheFallbackIsRemoved(): void
    {
        $verifier = new PasswordVerifier(legacyMd5Fallback: false);

        self::assertSame(PasswordMatch::None, $verifier->verify(self::PASSWORD, md5(self::PASSWORD)));
    }

    public function testEmptyStoredHashNeverMatches(): void
    {
        $verifier = new PasswordVerifier(legacyMd5Fallback: true);

        self::assertSame(PasswordMatch::None, $verifier->verify('', ''));
        self::assertSame(PasswordMatch::None, $verifier->verify(self::PASSWORD, ''));
    }

    public function testSameAsCurrentDetectsBothBcryptAndMd5WhateverTheFallback(): void
    {
        $verifier = new PasswordVerifier(legacyMd5Fallback: false);

        self::assertTrue($verifier->isSameAsCurrent(self::PASSWORD, password_hash(self::PASSWORD, PASSWORD_BCRYPT)));
        self::assertTrue($verifier->isSameAsCurrent(self::PASSWORD, md5(self::PASSWORD)));
        self::assertFalse($verifier->isSameAsCurrent('Another-Pass-42!', password_hash(self::PASSWORD, PASSWORD_BCRYPT)));
        self::assertFalse($verifier->isSameAsCurrent(self::PASSWORD, ''));
    }

    public function testLegacyMd5HashIsThirtyTwoHexadecimalCharacters(): void
    {
        self::assertTrue(PasswordVerifier::isLegacyMd5Hash(md5('x')));
        self::assertTrue(PasswordVerifier::isLegacyMd5Hash(strtoupper(md5('x'))));
        self::assertFalse(PasswordVerifier::isLegacyMd5Hash(password_hash('x', PASSWORD_BCRYPT)));
        self::assertFalse(PasswordVerifier::isLegacyMd5Hash(''));
        self::assertFalse(PasswordVerifier::isLegacyMd5Hash(str_repeat('z', 32)));
    }
}
