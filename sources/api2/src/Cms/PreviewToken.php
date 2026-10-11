<?php

namespace App\Cms;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Jeton de prévisualisation d'un contenu non publié (FEATURE_CMS.md § 5.2, CMS-12) : `{type}.{id}.{expiration}.{hmac}`,
 * signé avec le secret de l'application, valable 15 minutes.
 */
final class PreviewToken
{
    public const LIFETIME_SECONDS = 900;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
        private readonly ClockInterface $clock,
    ) {
    }

    public function create(string $type, int $id): string
    {
        $payload = sprintf('%s.%d.%d', $type, $id, $this->clock->now()->getTimestamp() + self::LIFETIME_SECONDS);

        return $payload . '.' . $this->sign($payload);
    }

    /** Le jeton est-il valide pour ce contenu ? */
    public function isValid(?string $token, string $type, int $id): bool
    {
        if ($token === null || substr_count($token, '.') !== 3) {
            return false;
        }
        [$tokenType, $tokenId, $expiresAt, $signature] = explode('.', $token);
        $payload = sprintf('%s.%s.%s', $tokenType, $tokenId, $expiresAt);

        return hash_equals($this->sign($payload), $signature)
            && $tokenType === $type
            && $tokenId === (string) $id
            && (int) $expiresAt >= $this->clock->now()->getTimestamp();
    }

    private function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, $this->secret);
    }
}
