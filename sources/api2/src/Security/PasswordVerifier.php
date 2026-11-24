<?php

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Compare un mot de passe saisi au hash de kp_user.Pwd (AUTH_HARDENING.md § 2bis).
 *
 * Le repli MD5 (AUTH-MD-02) est piloté par AUTH_LEGACY_MD5_FALLBACK : il reste actif jusqu'à la date
 * de retrait annoncée, puis on le coupe et `app:auth:purge-md5-hashes` vide les hash MD5 restants.
 */
final class PasswordVerifier
{
    private const LEGACY_MD5_PATTERN = '/^[0-9a-f]{32}$/i';

    public function __construct(
        #[Autowire('%env(bool:AUTH_LEGACY_MD5_FALLBACK)%')]
        private readonly bool $legacyMd5Fallback,
    ) {
    }

    public static function isLegacyMd5Hash(string $storedHash): bool
    {
        return preg_match(self::LEGACY_MD5_PATTERN, $storedHash) === 1;
    }

    public function isLegacyMd5FallbackEnabled(): bool
    {
        return $this->legacyMd5Fallback;
    }

    public function verify(string $plainPassword, string $storedHash): PasswordMatch
    {
        if ($storedHash === '') {
            return PasswordMatch::None;
        }
        if (password_verify($plainPassword, $storedHash)) {
            return PasswordMatch::Bcrypt;
        }
        if ($this->legacyMd5Fallback && $this->matchesLegacyMd5($plainPassword, $storedHash)) {
            return PasswordMatch::LegacyMd5;
        }

        return PasswordMatch::None;
    }

    /** Le nouveau mot de passe est-il l'ancien ? Vrai aussi pour un hash MD5, même repli coupé (AUTH-RN-04). */
    public function isSameAsCurrent(string $plainPassword, string $storedHash): bool
    {
        if ($storedHash === '') {
            return false;
        }

        return password_verify($plainPassword, $storedHash) || $this->matchesLegacyMd5($plainPassword, $storedHash);
    }

    private function matchesLegacyMd5(string $plainPassword, string $storedHash): bool
    {
        return self::isLegacyMd5Hash($storedHash) && hash_equals(strtolower($storedHash), md5($plainPassword));
    }
}
