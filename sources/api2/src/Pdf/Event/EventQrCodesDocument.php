<?php

namespace App\Pdf\Event;

use App\Pdf\Games\GamesRepository;
use App\Pdf\InvalidPdfRequest;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfQrCode;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;

/**
 * QR codes of a published event, to display on site (PDF_GOTENBERG.md § 7.5), public only: one card per link, with
 * its label, its QR code and its address written out.
 */
abstract class EventQrCodesDocument implements PdfDocumentProvider
{
    public function __construct(
        private readonly GamesRepository $games,
        private readonly PdfLabels $labels,
    ) {
    }

    public function supports(PdfTheme $theme): bool
    {
        return $theme === PdfTheme::Public;
    }

    public function options(): array
    {
        return [];
    }

    public function content(PdfRequest $request, PdfTheme $theme, ?array $allowedClubs): ?PdfContent
    {
        $id = $request->event ?? throw new InvalidPdfRequest('Parameter "event" is required.');
        $event = $this->games->event($id);
        if ($event === null || !$event['published']) {
            return null;
        }
        $labels = $this->labels->forLanguage($request->lang);

        $images = [];
        $cards = [];
        foreach ($this->links($id) as $index => [$labelKey, $url]) {
            $file = sprintf('qrcode-%d.svg', $index + 1);
            $images[$file] = PdfQrCode::svg($url);
            $cards[] = ['label' => $labels[$labelKey], 'url' => $url, 'file' => $file];
        }

        return new PdfContent(
            template: 'qrcodes.html.twig',
            title: $labels[$this->titleLabel()],
            filename: sprintf('%s_event-%d_%s.pdf', $this->document(), $id, $request->lang),
            variables: [
                'competition' => ['code' => '', 'title' => $event['title'], 'subtitle' => $event['place'] ?: null, 'logo' => null],
                'cards' => $cards,
            ],
            landscape: true,
            images: $images,
        );
    }

    /** Key of the title in config/pdf/labels.yaml. */
    abstract protected function titleLabel(): string;

    /** @return list<array{string, string}> label key and address of each link */
    abstract protected function links(int $event): array;
}
