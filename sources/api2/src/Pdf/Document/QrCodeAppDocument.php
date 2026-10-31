<?php

namespace App\Pdf\Document;

use App\Pdf\Event\EventQrCodesDocument;
use App\Pdf\Games\GamesRepository;
use App\Pdf\PdfCatalog;
use App\Pdf\PdfLabels;

/** The event in the results application, app2 (`qrcode-app`, ex-PdfQrCodeApp). */
final class QrCodeAppDocument extends EventQrCodesDocument
{
    public function __construct(GamesRepository $games, PdfLabels $labels, private readonly string $app2Url)
    {
        parent::__construct($games, $labels);
    }

    public function document(): string
    {
        return PdfCatalog::QRCODE_APP;
    }

    protected function titleLabel(): string
    {
        return 'qrcodes.application_title';
    }

    protected function links(int $event): array
    {
        return [['qrcodes.application', rtrim($this->app2Url, '/') . '/event/' . $event]];
    }
}
