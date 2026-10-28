<?php

namespace App\Pdf;

use Symfony\Component\HttpFoundation\Response;

/** Gotenberg unavailable, saturated or too slow (§ 6.2): 503 with Retry-After, or 504; never a stack trace. */
final class PdfRenderingFailed extends \RuntimeException
{
    public const RETRY_AFTER_SECONDS = 10;

    public static function unavailable(?\Throwable $previous = null): self
    {
        return new self('PDF rendering is unavailable, retry later.', Response::HTTP_SERVICE_UNAVAILABLE, $previous);
    }

    public static function timeout(?\Throwable $previous = null): self
    {
        return new self('PDF rendering took too long.', Response::HTTP_GATEWAY_TIMEOUT, $previous);
    }
}
