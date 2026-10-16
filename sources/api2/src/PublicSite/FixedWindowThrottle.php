<?php

namespace App\PublicSite;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Limite de débit à fenêtre fixe, compteur dans le cache applicatif (recherche, envoi de formulaires, envoi de
 * médias). Sans verrou : des requêtes simultanées peuvent dépasser la limite de quelques unités, ce qui suffit contre
 * les abus visés.
 */
class FixedWindowThrottle
{
    public function __construct(
        #[Autowire(service: 'cache.app')]
        private readonly CacheItemPoolInterface $cache,
        private readonly ClockInterface $clock,
    ) {
    }

    /** Compte un accès pour la clé ; renvoie le délai en secondes avant le prochain accès permis, ou null. */
    public function hit(string $key, int $limit, int $windowSeconds): ?int
    {
        $now = $this->clock->now()->getTimestamp();
        $window = intdiv($now, $windowSeconds);
        $item = $this->cache->getItem(sprintf('throttle.%s.%d', hash('xxh128', $key), $window));
        $count = (int) ($item->isHit() ? $item->get() : 0) + 1;
        $item->set($count);
        $item->expiresAfter($windowSeconds);
        $this->cache->save($item);

        return $count > $limit ? ($window + 1) * $windowSeconds - $now : null;
    }
}
