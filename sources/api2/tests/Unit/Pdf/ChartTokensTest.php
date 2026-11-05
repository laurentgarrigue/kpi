<?php

namespace App\Tests\Unit\Pdf;

use App\Pdf\ChartTokens;
use App\Pdf\PdfAssets;
use App\Pdf\PdfTheme;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The PDFs follow the charters of app4 and app3 (PDF_GOTENBERG.md § 8.1): PDF-DESIGN-01, -02, -05. */
final class ChartTokensTest extends TestCase
{
    private const ASSETS = __DIR__ . '/../../../assets/pdf';

    public function testOnlyTheLightThemeIsExtracted(): void
    {
        $css = <<<'CSS'
            /* --color-commented: #000; */
            :root {
              --color-primary: #1e4385;
              --ui-color-primary-500: oklch(0.540 0.116 260.93);
              --other: 1px;
            }
            @theme static {
              --font-display: 'Agency FB', Arial;
              --color-primary-50: var(--ui-color-primary-50);
            }
            .dark {
              --color-primary: #ffffff;
            }
            :root:not(.dark) [class*="bg-white"] {
              --color-ignored: #123456;
            }
            CSS;

        self::assertSame([
            '--color-primary' => '#1e4385',
            '--ui-color-primary-500' => 'oklch(0.540 0.116 260.93)',
            '--font-display' => "'Agency FB', Arial",
            '--color-primary-50' => 'var(--ui-color-primary-50)',
        ], ChartTokens::extract($css));
    }

    /** @return iterable<string, array{PdfTheme}> */
    public static function themes(): iterable
    {
        foreach (PdfTheme::cases() as $theme) {
            yield $theme->value => [$theme];
        }
    }

    #[DataProvider('themes')]
    public function testPdfDesign05TheCommittedTokensMatchTheCharter(PdfTheme $theme): void
    {
        $source = self::sourcesRoot() . '/' . ChartTokens::SOURCES[$theme->value];
        if (!is_file($source)) {
            self::markTestSkipped('Charter sources not reachable from here (api2 container without sources/).');
        }

        self::assertSame(
            ChartTokens::stylesheet($theme, (string) file_get_contents($source)),
            file_get_contents(self::ASSETS . '/tokens/' . $theme->value . '.css'),
            'The charter changed: run `make pdf_tokens_sync` and commit assets/pdf/.',
        );
    }

    public function testPdfDesign05TheCommittedFontsAndLogosMatchTheCharters(): void
    {
        foreach (PdfAssets::CHARTER_FILES as $published => $source) {
            $file = self::sourcesRoot() . '/' . $source;
            if (!is_file($file)) {
                self::markTestSkipped('Charter sources not reachable from here (api2 container without sources/).');
            }
            self::assertFileEquals($file, self::ASSETS . '/' . $published, $published);
        }
    }

    public function testPdfDesign02TheStylesheetHasNoLiteralColour(): void
    {
        $css = preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(self::ASSETS . '/' . PdfAssets::STYLESHEET));

        self::assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|\b(?:rgb|rgba|hsl|hsla|oklch|oklab)\(/i', (string) $css);
        self::assertDoesNotMatchRegularExpression('/:\s*(?:white|black|red|blue|green|grey|gray)\b/i', (string) $css);
    }

    public function testPdfDesign02TheTemplatesHaveNoStyleColour(): void
    {
        foreach (glob(__DIR__ . '/../../../templates/pdf/*.twig') ?: [] as $template) {
            // The Gotenberg footer is rendered apart from the page, without the stylesheet: no colour at all there.
            self::assertDoesNotMatchRegularExpression('/#[0-9a-f]{3,8}\b|\b(?:rgb|oklch)\(|color\s*:/i', (string) file_get_contents($template), basename($template));
        }
    }

    #[DataProvider('themes')]
    public function testPdfDesign01EveryThemeAliasResolvesToATokenOfItsCharter(PdfTheme $theme): void
    {
        $css = (string) file_get_contents(self::ASSETS . '/' . PdfAssets::STYLESHEET);
        self::assertMatchesRegularExpression('/\.theme-' . $theme->value . ' \{(.*?)\}/s', $css);
        preg_match('/\.theme-' . $theme->value . ' \{(.*?)\}/s', $css, $block);
        preg_match_all('/var\((--[a-z0-9-]+)\)/', $block[1], $used);

        $tokens = ChartTokens::extract((string) file_get_contents(self::ASSETS . '/tokens/' . $theme->value . '.css'));
        foreach (array_unique($used[1]) as $name) {
            self::assertArrayHasKey($name, $tokens, sprintf('%s is not a token of the %s charter', $name, $theme->value));
        }
    }

    public function testEveryOtherVariableIsAThemeAlias(): void
    {
        $css = (string) file_get_contents(self::ASSETS . '/' . PdfAssets::STYLESHEET);
        $outsideThemes = (string) preg_replace('/\.theme-(?:admin|public) \{.*?\}/s', '', $css);
        preg_match_all('/var\((--[a-z0-9-]+)\)/', $outsideThemes, $used);

        foreach (array_unique($used[1]) as $name) {
            self::assertStringStartsWith('--pdf-', $name, 'outside the theme blocks, only the --pdf-* aliases are used');
        }
    }

    /** sources/ seen from api2: the parent of the project in a checkout, LEGACY_DOCUMENT_ROOT in the container. */
    private static function sourcesRoot(): string
    {
        $fromProject = dirname(__DIR__, 4);

        return is_dir($fromProject . '/app4') ? $fromProject : (string) (getenv('LEGACY_DOCUMENT_ROOT') ?: $fromProject);
    }
}
