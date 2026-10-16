<?php

namespace App\PublicSite;

use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Limite de débit de la recherche globale : 30 requêtes par minute et par adresse IP (API_PUBLIC_TRANSVERSE.md
 * § 3.6).
 *
 * Les adresses privées (réseau Docker : rendu serveur d'app3, Traefik sans X-Forwarded-For) ne sont pas
 * limitées : app3 transmet l'adresse du visiteur dans X-Forwarded-For, que api2 lit grâce à trusted_proxies.
 */
class SearchThrottle
{
    public const LIMIT = 30;

    private const WINDOW_SECONDS = 60;

    public function __construct(private readonly FixedWindowThrottle $throttle)
    {
    }

    /** Compte une requête ; renvoie le délai en secondes avant la prochaine requête permise, ou null. */
    public function hit(?string $clientIp): ?int
    {
        if ($clientIp === null || IpUtils::isPrivateIp($clientIp)) {
            return null;
        }

        return $this->throttle->hit('public_search.' . $clientIp, self::LIMIT, self::WINDOW_SECONDS);
    }
}
