<?php

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * Façade au-dessus des deux rate-limiters `kpi_auth_login_ip` et `kpi_auth_login_user`.
 *
 * Chaque appel {@see consume()} tire un jeton sur les deux limiteurs. Si l'un des deux refuse,
 * le résultat renvoyé porte son `Retry-After` : le contrôleur n'a plus qu'à poser l'en-tête
 * et à répondre 429 (AUTH-RL-01 / AUTH-RL-02).
 *
 * Spec : {@see DOC/developer/in-progress/AUTH_HARDENING.md § 2.3}.
 */
final class LoginRateLimiter
{
    public function __construct(
        private readonly RateLimiterFactory $loginIpLimiter,
        private readonly RateLimiterFactory $loginUserLimiter,
    ) {
    }

    public function consume(Request $request, string $username): RateLimit
    {
        $byIp = $this->loginIpLimiter->create('ip:' . ($request->getClientIp() ?? 'unknown'))->consume();
        // Normalisation : insensible à la casse, trim. Évite qu'un attaquant contourne le quota
        // par compte en jouant sur les majuscules ou les espaces (le UserProvider les gère pareil).
        $byUser = $this->loginUserLimiter->create('user:' . strtolower(trim($username)))->consume();

        // On renvoie le plus restrictif des deux : si l'un refuse, c'est lui qui porte le Retry-After.
        if (!$byIp->isAccepted()) {
            return $byIp;
        }
        return $byUser;
    }
}
