<?php

namespace App\Cms;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Purge du cache des pages d'app3 après une publication (FEATURE_CMS.md § 5.4, CMS-07) : `POST {app3}/_internal/purge`
 * sur le réseau Docker, avec un secret partagé. Au mieux : sans app3 configuré ou en cas d'erreur, rien n'échoue,
 * le cache expire seul (60 s).
 */
final class App3CachePurger
{
    private const TIMEOUT_SECONDS = 2;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::APP3_INTERNAL_URL)%')]
        private readonly ?string $app3InternalUrl,
        #[Autowire('%env(default::APP3_PURGE_TOKEN)%')]
        private readonly ?string $purgeToken,
    ) {
    }

    /** @param list<string> $paths chemins sans préfixe de langue (app3 purge aussi /en/…) */
    public function purge(array $paths): bool
    {
        if (($this->app3InternalUrl ?? '') === '' || ($this->purgeToken ?? '') === '' || $paths === []) {
            return false;
        }
        try {
            $response = $this->httpClient->request('POST', rtrim((string) $this->app3InternalUrl, '/') . '/_internal/purge', [
                'headers' => ['Authorization' => 'Bearer ' . $this->purgeToken],
                'json' => ['paths' => array_values(array_unique($paths))],
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            return $response->getStatusCode() < 300;
        } catch (\Throwable $error) {
            $this->logger->warning('Purge du cache app3 impossible : {message}', ['message' => $error->getMessage()]);

            return false;
        }
    }
}
