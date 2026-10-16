<?php

namespace App\Forms;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Jeton anti-robot remis avec la définition d'un formulaire (FEATURE_FORMS.md § 5) : `{émis}.{hmac}`. Un envoi
 * moins de 3 secondes après l'affichage (remplissage automatique) ou plus d'un jour après est refusé.
 */
final class FormToken
{
    public const MIN_SECONDS = 3;
    public const MAX_SECONDS = 86400;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        private readonly string $secret,
        private readonly ClockInterface $clock,
    ) {
    }

    public function issue(string $slug): string
    {
        $issuedAt = (string) $this->clock->now()->getTimestamp();

        return $issuedAt . '.' . $this->sign($slug, $issuedAt);
    }

    /** @return string|null code d'erreur (`invalid`, `too_fast`, `expired`) ou null si le jeton est valable */
    public function check(string $slug, ?string $token): ?string
    {
        if ($token === null || substr_count($token, '.') !== 1) {
            return 'invalid';
        }
        [$issuedAt, $signature] = explode('.', $token);
        if (!ctype_digit($issuedAt) || !hash_equals($this->sign($slug, $issuedAt), $signature)) {
            return 'invalid';
        }
        $age = $this->clock->now()->getTimestamp() - (int) $issuedAt;
        if ($age < self::MIN_SECONDS) {
            return 'too_fast';
        }

        return $age > self::MAX_SECONDS ? 'expired' : null;
    }

    private function sign(string $slug, string $issuedAt): string
    {
        return hash_hmac('sha256', 'form.' . $slug . '.' . $issuedAt, $this->secret);
    }
}
