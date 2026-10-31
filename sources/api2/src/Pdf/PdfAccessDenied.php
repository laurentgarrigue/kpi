<?php

namespace App\Pdf;

/**
 * Raised by a document provider when the data it resolves (team, gameday…) lies out of the perimeter of the user,
 * although the parameters of the request were in it: answered 403 (PDF-AUTH-04).
 */
final class PdfAccessDenied extends \RuntimeException
{
}
