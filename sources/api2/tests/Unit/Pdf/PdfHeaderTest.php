<?php

namespace App\Tests\Unit\Pdf;

use App\Pdf\PdfHeader;
use App\Pdf\PdfTheme;
use PHPUnit\Framework\TestCase;

/** Visuals of the competition in the header and footer (PDF_GOTENBERG.md § 8.4.1, PDF-DESIGN-04 and 09). */
final class PdfHeaderTest extends TestCase
{
    private const PNG = "\x89PNG\r\n\x1a\n\0\0\0\rIHDR\0\0\0\x01\0\0\0\x01\x08\x06\0\0\0\x1f\x15\xc4\x89\0\0\0\rIDATx\x9cc\xf8\x0f\0\0\x01\x01\0\x05\x18\xd8N\0\0\0\0IEND\xaeB`\x82";

    public function testPdfDesign09TheBannerReplacesEveryLogo(): void
    {
        $competition = self::competition(banner: 'banner.png', logo: 'logo.png');

        self::assertSame([], PdfHeader::charterLogos(PdfTheme::Public, $competition));
        self::assertSame([], PdfHeader::charterLogos(PdfTheme::Admin, $competition));
        self::assertNull(PdfHeader::competitionLogo($competition));
    }

    public function testTheLogoGoesWithTheCharterWhenKpiFfckIsTicked(): void
    {
        self::assertSame(['logo-ffck.png', 'logo-cna.png'], PdfHeader::charterLogos(PdfTheme::Public, self::competition(logo: 'logo.png')));
        self::assertSame([], PdfHeader::charterLogos(PdfTheme::Public, self::competition(logo: 'logo.png', charter: false)));
        self::assertSame(['logo-kpi.png'], PdfHeader::charterLogos(PdfTheme::Admin, self::competition(logo: 'logo.png')));
        self::assertSame('logo.png', PdfHeader::competitionLogo(self::competition(logo: 'logo.png')));
    }

    public function testPdfDesign04WithoutBannerNorLogoPublicPdfsAlwaysCarryFfckAndCna(): void
    {
        self::assertSame(['logo-ffck.png', 'logo-cna.png'], PdfHeader::charterLogos(PdfTheme::Public, self::competition(charter: false)));
        self::assertSame([], PdfHeader::charterLogos(PdfTheme::Admin, self::competition(charter: false)), 'admin: title only');
        self::assertSame(['logo-kpi.png'], PdfHeader::charterLogos(PdfTheme::Admin, self::competition()));
        self::assertSame(['logo-kpi.png'], PdfHeader::charterLogos(PdfTheme::Admin, null), 'document without competition');
    }

    public function testTheSponsorIsInlinedForTheFooter(): void
    {
        $uri = PdfHeader::sponsorDataUri(self::competition(sponsor: 'sponsor.png'), ['sponsor.png' => self::PNG]);

        self::assertSame('data:image/png;base64,' . base64_encode(self::PNG), $uri);
        self::assertNull(PdfHeader::sponsorDataUri(self::competition(), ['sponsor.png' => self::PNG]), 'not ticked or missing');
        self::assertNull(PdfHeader::sponsorDataUri(self::competition(sponsor: 'sponsor.png'), []));
    }

    /** @return array<string, mixed> */
    private static function competition(?string $banner = null, ?string $logo = null, ?string $sponsor = null, bool $charter = true): array
    {
        return ['code' => 'N1H', 'title' => 'N1H', 'banner' => $banner, 'logo' => $logo, 'sponsor' => $sponsor, 'charter' => $charter];
    }
}
