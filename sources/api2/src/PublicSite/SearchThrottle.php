<?php

namespace App\PublicSite;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Limite de débit de la recherche globale : 30 requêtes par minute et par adresse IP (API_PUBLIC_TRANSVERSE.md
 * § 3.6). Fenêtre fixe d'une minute, compteur dans le cache applicatif. Sans verrou : des requêtes simultanées
 * peuvent dépasser la limite de quelques unités, ce qui suffit contre l'aspiration.
 *
 * Les adresses privées (réseau Docker : rendu serveur d'app3, Traefik sans X-Forwarded-For) ne sont pas
 * limitées : app3 transmet l'adresse du visiteur dans X-Forwarded-For, que api2 lit grâce à trusted_proxies.
 */
class SearchThrottle
{
    public const LIMIT = 30;

    private const WINDOW_SECONDS = 60;

    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Compte une requête ; renvoie le délai en secondes avant la prochaine requête permise, ou null. */
    public function hit(?string $clientIp): ?int
    {
        if ($clientIp === null || IpUtils::isPrivateIp($clientIp)) {
            return null;
        }

        $now = $this->clock->now()->getTimestamp();
        $window = intdiv($now, self::WINDOW_SECONDS);
        $item = $this->cache->getItem(sprintf('public_search.%s.%d', hash('xxh128', $clientIp), $window));
        $count = (int) ($item->isHit() ? $item->get() : 0) + 1;
        $item->set($count);
        $item->expiresAfter(self::WINDOW_SECONDS);
        $this->cache->save($item);

        return $count > self::LIMIT ? ($window + 1) * self::WINDOW_SECONDS - $now : null;
    }
}
