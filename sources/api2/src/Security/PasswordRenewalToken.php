<?php

namespace App\Security;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Jeton remis par /auth/login à un compte « mot de passe à renouveler » (AUTH-RN-02) :
 * `{base64(code)}.{expiration}.{hmac}`, valable 15 minutes.
 *
 * La signature inclut le hash courant du mot de passe : dès que le mot de passe change, le jeton
 * ne vaut plus rien (usage unique sans stockage).
 */
final class PasswordRenewalToken
{
    public const LIFETIME_SECONDS = 900;
    private const PARTS = 3;
    /** kp_user.Code est un VARCHAR(8). */
    private const USER_CODE_MAX_LENGTH = 8;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
        private readonly ClockInterface $clock,
    ) {
    }

    public function create(string $userCode, string $storedHash): string
    {
        $payload = base64_encode($userCode) . '.' . ($this->clock->now()->getTimestamp() + self::LIFETIME_SECONDS);

        return $payload . '.' . $this->sign($payload, $storedHash);
    }

    /** Code utilisateur porté par le jeton, sans vérifier la signature (il faut d'abord lire son hash). */
    public function userCode(string $token): ?string
    {
        $parts = explode('.', $token);
        if (count($parts) !== self::PARTS) {
            return null;
        }
        $code = base64_decode($parts[0], true);
        $isUserCode = is_string($code) && $code !== '' && mb_check_encoding($code, 'UTF-8')
            && mb_strlen($code) <= self::USER_CODE_MAX_LENGTH;

        return $isUserCode ? $code : null;
    }

    public function isValid(string $token, string $storedHash): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== self::PARTS) {
            return false;
        }
        [$encodedCode, $expiresAt, $signature] = $parts;

        return hash_equals($this->sign($encodedCode . '.' . $expiresAt, $storedHash), $signature)
            && (int) $expiresAt >= $this->clock->now()->getTimestamp();
    }

    private function sign(string $payload, string $storedHash): string
    {
        return hash_hmac('sha256', $payload . '.' . $storedHash, $this->secret);
    }
}
