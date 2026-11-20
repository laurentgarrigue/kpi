<?php

namespace App\Tests\Unit\Security;

use App\Security\SecondFactorSecretBox;
use PHPUnit\Framework\TestCase;

/** Chiffrement des secrets TOTP avec AUTH_2FA_ENCRYPTION_KEY ({@see AUTH_HARDENING.md § 5.5}). */
final class SecondFactorSecretBoxTest extends TestCase
{
    public function testEncryptsAndDecrypts(): void
    {
        $box = new SecondFactorSecretBox(base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
        $sealed = $box->encrypt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ');

        self::assertStringNotContainsString('GEZDGNBV', $sealed);
        self::assertSame('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', $box->decrypt($sealed));
        self::assertNotSame($sealed, $box->encrypt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ'), 'nonce aléatoire');
    }

    public function testAnotherKeyCannotDecrypt(): void
    {
        $sealed = (new SecondFactorSecretBox(base64_encode(random_bytes(32))))->encrypt('secret');

        self::assertNull((new SecondFactorSecretBox(base64_encode(random_bytes(32))))->decrypt($sealed));
    }

    public function testMissingOrMalformedKeyMeansNotConfigured(): void
    {
        self::assertFalse((new SecondFactorSecretBox(''))->isConfigured());
        self::assertFalse((new SecondFactorSecretBox(base64_encode('too-short')))->isConfigured());
        self::assertTrue((new SecondFactorSecretBox(base64_encode(random_bytes(32))))->isConfigured());
    }
}
