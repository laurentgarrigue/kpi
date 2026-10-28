<?php

namespace App\Pdf\Ranking;

/**
 * Which ranking a document reads (PDF_GOTENBERG.md § 7.4): the published one (`*_publi` columns), the only one a
 * public PDF may read, or the computed one, printed on admin PDFs with the « Provisional » watermark.
 */
enum RankingSource
{
    case Published;
    case Computed;

    public function column(string $name): string
    {
        return $this === self::Published ? $name . '_publi' : $name;
    }

    public function isProvisional(): bool
    {
        return $this === self::Computed;
    }
}
