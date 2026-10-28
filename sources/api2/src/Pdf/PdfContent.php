<?php

namespace App\Pdf;

/** What a document provider hands to the renderer: a Twig template under templates/pdf/ and its data. */
final class PdfContent
{
    /**
     * @param array<string, mixed> $variables  template variables
     * @param string|null          $publicPath path of the equivalent page of the public website (app3), for its QR code
     * @param array<string, string> $images     images of the legacy media tree used by the page, by file name
     */
    public function __construct(
        public readonly string $template,
        public readonly string $title,
        public readonly string $filename,
        public readonly array $variables,
        public readonly bool $landscape = false,
        public readonly ?string $publicPath = null,
        public readonly array $images = [],
    ) {
    }

    /** Fingerprint of the data, for the ETag of public PDFs (§ 6.2): same data, same document. */
    public function fingerprint(): string
    {
        return hash('xxh128', serialize([$this->template, $this->title, $this->variables, $this->landscape, $this->publicPath, array_map('md5', $this->images)]));
    }
}
