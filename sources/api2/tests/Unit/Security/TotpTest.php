<?php

namespace App\Tests\Unit\Security;

use App\Security\Totp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * TOTP RFC 6238 ({@see DOC/developer/in-progress/AUTH_HARDENING.md § 5 — AUTH-2FA-04, AUTH-2FA-09}).
 * Vecteurs de l'annexe B de la RFC (SHA-1, secret ASCII « 12345678901234567890 »), tronqués à 6 chiffres.
 */
final class TotpTest extends TestCase
{
    /** base32("12345678901234567890") */
    private const RFC_SECRET = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

    /** @return iterable<string, array{int, string}> */
    public static function rfcVectors(): iterable
    {
        yield 'T=59' => [59, '287082'];
        yield 'T=1111111109' => [1111111109, '081804'];
        yield 'T=1111111111' => [1111111111, '050471'];
        yield 'T=1234567890' => [1234567890, '005924'];
        yield 'T=2000000000' => [2000000000, '279037'];
        yield 'T=20000000000' => [20000000000, '353130'];
    }

    #[DataProvider('rfcVectors')]
    public function testCodeMatchesTheRfcVectors(int $unixTime, string $expected): void
    {
        self::assertSame($expected, Totp::codeAt(self::RFC_SECRET, $unixTime));
    }

    public function testVerifyAcceptsTheCurrentAndTheAdjacentPeriodsOnly(): void
    {
        $now = 1_800_000_000;
        $current = intdiv($now, Totp::PERIOD_SECONDS);

        self::assertSame($current, Totp::verify(self::RFC_SECRET, Totp::codeAt(self::RFC_SECRET, $now), $now, null));
        self::assertSame($current - 1, Totp::verify(self::RFC_SECRET, Totp::codeAt(self::RFC_SECRET, $now - 30), $now, null));
        self::assertSame($current + 1, Totp::verify(self::RFC_SECRET, Totp::codeAt(self::RFC_SECRET, $now + 30), $now, null));
        self::assertNull(Totp::verify(self::RFC_SECRET, Totp::codeAt(self::RFC_SECRET, $now - 60), $now, null));
        self::assertNull(Totp::verify(self::RFC_SECRET, Totp::codeAt(self::RFC_SECRET, $now + 60), $now, null));
    }

    public function testVerifyRefusesACodeAlreadyUsed(): void
    {
        $now = 1_800_000_000;
        $code = Totp::codeAt(self::RFC_SECRET, $now);
        $used = Totp::verify(self::RFC_SECRET, $code, $now, null);

        self::assertNull(Totp::verify(self::RFC_SECRET, $code, $now, $used));
    }

    public function testVerifyIgnoresSpacesAndRefusesMalformedCodes(): void
    {
        $now = 1_800_000_000;
        $code = Totp::codeAt(self::RFC_SECRET, $now);

        self::assertNotNull(Totp::verify(self::RFC_SECRET, substr($code, 0, 3) . ' ' . substr($code, 3), $now, null));
        self::assertNull(Totp::verify(self::RFC_SECRET, 'abcdef', $now, null));
        self::assertNull(Totp::verify(self::RFC_SECRET, '12345', $now, null));
    }

    public function testGeneratedSecretIs160BitsOfBase32(): void
    {
        $secret = Totp::generateSecret();

        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);
        self::assertNotSame($secret, Totp::generateSecret());
    }

    public function testBase32RoundTrip(): void
    {
        self::assertSame(self::RFC_SECRET, Totp::base32Encode('12345678901234567890'));
        self::assertSame('12345678901234567890', Totp::base32Decode(self::RFC_SECRET));
    }

    public function testProvisioningUriNamesTheIssuerAndTheAccount(): void
    {
        $uri = Totp::provisioningUri(self::RFC_SECRET, 'TADMIN', 'KPI');

        self::assertSame(
            'otpauth://totp/KPI:TADMIN?secret=' . self::RFC_SECRET . '&issuer=KPI&algorithm=SHA1&digits=6&period=30',
            $uri,
        );
    }
}
