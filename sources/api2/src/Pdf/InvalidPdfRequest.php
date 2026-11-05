<?php

namespace App\Pdf;

/** Missing, unknown or malformed parameter of a PDF request: answered 400 with the message (§ 6.1). */
final class InvalidPdfRequest extends \InvalidArgumentException
{
}
