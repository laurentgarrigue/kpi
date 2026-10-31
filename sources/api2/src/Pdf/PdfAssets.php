<?php

namespace App\Pdf;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Files sent to Gotenberg with every page (PDF_GOTENBERG.md § 8): stylesheet, charter tokens, fonts and logos, copied
 * under assets/pdf/ by `app:pdf:sync-assets`, plus the images of the legacy media tree. Nothing is fetched by
 * Chromium (§ 9).
 */
class PdfAssets
{
    public const STYLESHEET = 'pdf.css';

    /** Copied from the charters by `app:pdf:sync-assets`: published name => source, relative to the legacy tree. */
    public const CHARTER_FILES = [
        'fonts/raleway-latin.woff2' => 'kpi-layer/public/fonts/raleway/raleway-latin-wght-normal.woff2',
        'fonts/raleway-latin-ext.woff2' => 'kpi-layer/public/fonts/raleway/raleway-latin-ext-wght-normal.woff2',
        'fonts/agencyfb.ttf' => 'kpi-layer/public/fonts/agency-fb/agencyfb.ttf',
        'img/logo-kpi.png' => 'app4/public/img/logo_kp.png',
        'img/logo-ffck.png' => 'kpi-layer/public/img/brand/ffck.png',
        'img/logo-cna.png' => 'kpi-layer/public/img/brand/cna-kayak-polo.png',
    ];

    /** Logos of each charter (§ 8.3, § 8.4, PDF-DESIGN-04). */
    public const LOGOS = [
        PdfTheme::Admin->value => ['logo-kpi.png'],
        PdfTheme::Public->value => ['logo-ffck.png', 'logo-cna.png'],
    ];

    private const FONTS = ['raleway-latin.woff2', 'raleway-latin-ext.woff2', 'agencyfb.ttf'];

    public function __construct(
        #[Autowire('%kernel.project_dir%/assets/pdf')]
        private readonly string $directory,
        private readonly string $legacyDocumentRoot,
    ) {
    }

    public function directory(): string
    {
        return $this->directory;
    }

    public static function tokensFile(PdfTheme $theme): string
    {
        return sprintf('tokens-%s.css', $theme->value);
    }

    /** @return array<string, string> files of the theme, by the name the page uses */
    public function files(PdfTheme $theme): array
    {
        $files = [
            self::STYLESHEET => $this->read(self::STYLESHEET),
            self::tokensFile($theme) => $this->read('tokens/' . $theme->value . '.css'),
        ];
        foreach (self::FONTS as $font) {
            $files[$font] = $this->read('fonts/' . $font);
        }
        foreach (self::LOGOS[$theme->value] as $logo) {
            $files[$logo] = $this->read('img/' . $logo);
        }

        return $files;
    }

    /**
     * Image of the legacy media tree (path relative to /img/, as the public endpoints give it), or null when it is a
     * remote URL or a missing file: Chromium never fetches anything.
     *
     * @return array{name: string, content: string}|null
     */
    public function legacyImage(?string $path): ?array
    {
        if ($path === null || $path === '' || str_contains($path, '://') || str_contains($path, '..')) {
            return null;
        }
        $file = $this->legacyDocumentRoot . '/img/' . $path;
        if (!is_file($file)) {
            return null;
        }

        return ['name' => 'media-' . hash('xxh64', $path) . '.' . pathinfo($file, PATHINFO_EXTENSION), 'content' => (string) file_get_contents($file)];
    }

    private function read(string $relativePath): string
    {
        $content = @file_get_contents($this->directory . '/' . $relativePath);
        if ($content === false) {
            throw new \LogicException(sprintf('PDF asset "%s" is missing: run bin/console app:pdf:sync-assets.', $relativePath));
        }

        return $content;
    }
}
