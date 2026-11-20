<?php

namespace App\Security;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Jeton remis par /auth/login quand il n'ouvre pas de session (AUTH_HARDENING.md § 5.5) :
 * `{but}.{base64(code)}.{expiration}.{hmac}`, valable 10 minutes, gardé en mémoire par app4.
 *
 * - CHALLENGE : le mot de passe est bon, un code du second facteur est attendu (AUTH-2FA-07) ;
 * - ENROLL : la grâce est passée, le compte doit s'enrôler avant d'entrer (AUTH-2FA-02).
 *
 * Comme {@see PasswordRenewalToken}, la signature inclut le hash courant du mot de passe : le jeton meurt dès que
 * le mot de passe change.
 */
final class SecondFactorToken
{
    public const CHALLENGE = 'challenge';
    public const ENROLL = 'enroll';
    public const LIFETIME_SECONDS = 600;

    private const PARTS = 4;
    /** kp_user.Code est un VARCHAR(8). */
    private const USER_CODE_MAX_LENGTH = 8;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
        private readonly ClockInterface $clock,
    ) {
    }

    public function create(string $purpose, string $userCode, string $storedHash): string
    {
        $payload = $purpose . '.' . base64_encode($userCode) . '.' . ($this->clock->now()->getTimestamp() + self::LIFETIME_SECONDS);

        return $payload . '.' . $this->sign($payload, $storedHash);
    }

    /** Code utilisateur porté par le jeton, sans vérifier la signature (il faut d'abord lire son hash). */
    public function userCode(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== self::PARTS) {
            return null;
        }
        $code = base64_decode($parts[1], true);
        $isUserCode = is_string($code) && $code !== '' && mb_check_encoding($code, 'UTF-8')
            && mb_strlen($code) <= self::USER_CODE_MAX_LENGTH;

        return $isUserCode ? $code : null;
    }

    public function isValid(string $token, string $purpose, string $storedHash): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== self::PARTS) {
            return false;
        }
        [$tokenPurpose, $encodedCode, $expiresAt, $signature] = $parts;

        return $tokenPurpose === $purpose
            && hash_equals($this->sign($tokenPurpose . '.' . $encodedCode . '.' . $expiresAt, $storedHash), $signature)
            && (int) $expiresAt >= $this->clock->now()->getTimestamp();
    }

    private function sign(string $payload, string $storedHash): string
    {
        return hash_hmac('sha256', $payload . '.' . $storedHash, $this->secret);
    }
}
