<?php

namespace App\Pdf;

use App\PublicSite\FixedWindowThrottle;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * Rate limit of the public PDFs (PDF_GOTENBERG.md § 9, PDF-PERF-02): rendering is costly, 30 documents per minute
 * and per IP address. Private addresses (Docker network, server-side rendering of app3) are not limited, as for
 * the search (SearchThrottle).
 */
class PublicPdfThrottle
{
    public const LIMIT = 30;

    private const WINDOW_SECONDS = 60;

    public function __construct(private readonly FixedWindowThrottle $throttle)
    {
    }

    /** Counts a request; returns the delay in seconds before the next allowed one, or null. */
    public function hit(?string $clientIp): ?int
    {
        if ($clientIp === null || IpUtils::isPrivateIp($clientIp)) {
            return null;
        }

        return $this->throttle->hit('public_pdf.' . $clientIp, self::LIMIT, self::WINDOW_SECONDS);
    }
}
