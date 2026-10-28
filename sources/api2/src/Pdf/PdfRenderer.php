<?php

namespace App\Pdf;

use Psr\Clock\ClockInterface;
use Twig\Environment;

/**
 * Turns the content of a document into a PDF (PDF_GOTENBERG.md § 5): Twig template in the charter of the audience,
 * fixed texts in the language of the request, footer with the page numbers, then Gotenberg.
 */
class PdfRenderer
{
    private const FOOTER_TEMPLATE = 'pdf/footer.html.twig';

    public function __construct(
        private readonly Environment $twig,
        private readonly GotenbergClient $gotenberg,
        private readonly PdfAssets $assets,
        private readonly PdfLabels $labels,
        private readonly ClockInterface $clock,
        private readonly string $publicSiteUrl,
    ) {
    }

    /** @throws PdfRenderingFailed */
    public function render(PdfContent $content, PdfTheme $theme, PdfRequest $request): string
    {
        $files = $this->assets->files($theme);
        $context = $this->context($content, $theme, $request);

        foreach ($content->images as $name => $image) {
            $files[$name] = $image;
        }
        if ($context['qr_url'] !== null) {
            $files[PdfQrCode::FILE] = PdfQrCode::svg($context['qr_url']);
        }

        return $this->gotenberg->render(
            $this->twig->render('pdf/' . $content->template, $context),
            $this->twig->render(self::FOOTER_TEMPLATE, $context),
            $files,
            $content->landscape,
        );
    }

    /** The page as Gotenberg receives it, for the dev-only preview (§ 8.6). */
    public function html(PdfContent $content, PdfTheme $theme, PdfRequest $request): string
    {
        return $this->twig->render('pdf/' . $content->template, $this->context($content, $theme, $request));
    }

    /** @return array<string, mixed> */
    private function context(PdfContent $content, PdfTheme $theme, PdfRequest $request): array
    {
        $qrUrl = $theme === PdfTheme::Public && $content->publicPath !== null
            ? rtrim($this->publicSiteUrl, '/') . $content->publicPath
            : null;

        return [
            ...$content->variables,
            'title' => $content->title,
            'theme' => $theme->value,
            'lang' => $request->lang,
            'season' => $request->season,
            'labels' => $this->labels->forLanguage($request->lang),
            'tokens_file' => PdfAssets::tokensFile($theme),
            'stylesheet' => PdfAssets::STYLESHEET,
            'logos' => PdfAssets::LOGOS[$theme->value],
            'generated_at' => $this->clock->now()->setTimezone(new \DateTimeZone($request->timezone)),
            'qr_url' => $qrUrl,
            'qr_file' => $qrUrl === null ? null : PdfQrCode::FILE,
        ];
    }
}
