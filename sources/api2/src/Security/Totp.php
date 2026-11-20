<?php

namespace App\Security;

/**
 * TOTP (RFC 6238) : HMAC-SHA1, 6 chiffres, période de 30 s (AUTH_HARDENING.md § 5, AUTH-2FA-04 et 09).
 *
 * Logique pure, testée sur les vecteurs de la RFC. Les applications d'authentification (Google Authenticator,
 * Authy, 1Password…) attendent exactement ces paramètres : ne pas les changer.
 */
final class Totp
{
    public const PERIOD_SECONDS = 30;
    public const DIGITS = 6;
    /** Écart d'horloge toléré, en nombre de périodes de part et d'autre. */
    public const DRIFT_PERIODS = 1;

    private const SECRET_BYTES = 20;
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    private const BITS_PER_BASE32_CHAR = 5;
    private const BITS_PER_BYTE = 8;

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(self::SECRET_BYTES));
    }

    public static function codeAt(string $base32Secret, int $unixTime): string
    {
        return self::codeForTimestep($base32Secret, intdiv($unixTime, self::PERIOD_SECONDS));
    }

    /**
     * Renvoie le pas de temps reconnu (à mémoriser pour refuser un rejeu), ou null si le code ne correspond à
     * aucune période tolérée ou à une période déjà utilisée.
     */
    public static function verify(string $base32Secret, string $code, int $unixTime, ?int $lastUsedTimestep): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (preg_match('/^\d{' . self::DIGITS . '}$/', $code) !== 1) {
            return null;
        }

        $current = intdiv($unixTime, self::PERIOD_SECONDS);
        for ($timestep = $current - self::DRIFT_PERIODS; $timestep <= $current + self::DRIFT_PERIODS; $timestep++) {
            $isFresh = $lastUsedTimestep === null || $timestep > $lastUsedTimestep;
            if ($isFresh && hash_equals(self::codeForTimestep($base32Secret, $timestep), $code)) {
                return $timestep;
            }
        }

        return null;
    }

    public static function provisioningUri(string $base32Secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            rawurlencode($issuer),
            rawurlencode($account),
            $base32Secret,
            rawurlencode($issuer),
            self::DIGITS,
            self::PERIOD_SECONDS,
        );
    }

    public static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), self::BITS_PER_BYTE, '0', STR_PAD_LEFT);
        }

        $encoded = '';
        foreach (str_split($bits, self::BITS_PER_BASE32_CHAR) as $chunk) {
            $encoded .= self::BASE32_ALPHABET[bindec(str_pad($chunk, self::BITS_PER_BASE32_CHAR, '0'))];
        }

        return $encoded;
    }

    public static function base32Decode(string $base32): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($base32, '='))) as $char) {
            $value = strpos(self::BASE32_ALPHABET, $char);
            if ($value === false) {
                return '';
            }
            $bits .= str_pad(decbin($value), self::BITS_PER_BASE32_CHAR, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, self::BITS_PER_BYTE) as $chunk) {
            if (strlen($chunk) === self::BITS_PER_BYTE) {
                $bytes .= chr(bindec($chunk));
            }
        }

        return $bytes;
    }

    private static function codeForTimestep(string $base32Secret, int $timestep): string
    {
        $hmac = hash_hmac('sha1', pack('J', $timestep), self::base32Decode($base32Secret), true);
        $offset = ord($hmac[strlen($hmac) - 1]) & 0x0F;
        $binary = ((ord($hmac[$offset]) & 0x7F) << 24)
            | (ord($hmac[$offset + 1]) << 16)
            | (ord($hmac[$offset + 2]) << 8)
            | ord($hmac[$offset + 3]);

        return str_pad((string) ($binary % 10 ** self::DIGITS), self::DIGITS, '0', STR_PAD_LEFT);
    }
}
