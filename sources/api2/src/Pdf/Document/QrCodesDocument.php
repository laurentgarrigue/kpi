<?php

namespace App\Pdf\Document;

use App\Pdf\Event\EventQrCodesDocument;
use App\Pdf\Games\GamesRepository;
use App\Pdf\PdfCatalog;
use App\Pdf\PdfCompetitions;
use App\Pdf\PdfLabels;

/** Links of an event on the public website, its games and its progress (`qrcodes`, ex-PdfQrCodes). */
final class QrCodesDocument extends EventQrCodesDocument
{
    public function __construct(GamesRepository $games, PdfCompetitions $competitions, PdfLabels $labels, private readonly string $publicSiteUrl)
    {
        parent::__construct($games, $competitions, $labels);
    }

    public function document(): string
    {
        return PdfCatalog::QRCODES;
    }

    protected function titleLabel(): string
    {
        return 'qrcodes.title';
    }

    protected function links(int $event): array
    {
        $base = rtrim($this->publicSiteUrl, '/') . '/events/' . $event;

        return [['qrcodes.games', $base . '/games'], ['qrcodes.progress', $base . '/progress']];
    }
}
