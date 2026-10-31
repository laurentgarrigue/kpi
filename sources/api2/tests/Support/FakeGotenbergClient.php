<?php

namespace App\Tests\Support;

use App\Pdf\GotenbergClient;
use App\Pdf\PdfRenderingFailed;

/** Gotenberg stand-in of the test environment: keeps what it is asked to render, answers a minimal PDF. */
final class FakeGotenbergClient extends GotenbergClient
{
    public const PDF = "%PDF-1.4\n% fake\n";

    public ?string $html = null;
    public ?string $footer = null;

    /** @var array<string, string> */
    public array $files = [];

    public bool $landscape = false;
    public bool $unavailable = false;

    public function __construct()
    {
    }

    public function render(string $html, string $footer, array $files, bool $landscape): string
    {
        if ($this->unavailable) {
            throw PdfRenderingFailed::unavailable();
        }
        $this->html = $html;
        $this->footer = $footer;
        $this->files = $files;
        $this->landscape = $landscape;

        return self::PDF;
    }
}
