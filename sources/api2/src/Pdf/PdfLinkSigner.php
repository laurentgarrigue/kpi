<?php

namespace App\Pdf;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Short-lived signed links to a PDF (PDF_GOTENBERG.md § 6.3-2): an HMAC-SHA256 of the path and of every parameter,
 * with an expiry. The same scheme is checked by the legacy pages (sources/commun/MySignedLink.php, phase 0), which
 * share the secret: keep both in step.
 */
class PdfLinkSigner
{
    public const TTL_SECONDS = 300;
    public const EXPIRES = 'expires';
    public const SIGNATURE = 'signature';

    public function __construct(
        #[Autowire('%env(PDF_LINK_SECRET)%')]
        private readonly string $secret,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @param array<string, string|int> $parameters
     *
     * @return array<string, string> the parameters, with the expiry and the signature
     */
    public function sign(string $path, array $parameters): array
    {
        $parameters = array_map('strval', $parameters);
        $parameters[self::EXPIRES] = (string) ($this->clock->now()->getTimestamp() + self::TTL_SECONDS);
        $parameters[self::SIGNATURE] = $this->signature($path, $parameters);

        return $parameters;
    }

    /** @param array<string, mixed> $parameters query of the request, signature included */
    public function isValid(string $path, array $parameters): bool
    {
        $signature = $parameters[self::SIGNATURE] ?? null;
        $expires = $parameters[self::EXPIRES] ?? null;
        unset($parameters[self::SIGNATURE]);

        return is_string($signature)
            && is_string($expires) && ctype_digit($expires)
            && (int) $expires >= $this->clock->now()->getTimestamp()
            && $this->secret !== ''
            && hash_equals($this->signature($path, $parameters), $signature);
    }

    /** @param array<string, mixed> $parameters */
    private function signature(string $path, array $parameters): string
    {
        ksort($parameters);
        $payload = $path . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

        return rtrim(strtr(base64_encode(hash_hmac('sha256', $payload, $this->secret, true)), '+/', '-_'), '=');
    }
}
