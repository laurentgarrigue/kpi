<?php

namespace App\Pdf;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;

/** QR code of a public PDF towards its equivalent page of the public website (§ 8.4, PDF-DESIGN-08). */
final class PdfQrCode
{
    public const FILE = 'qrcode.svg';

    private const SIZE = 240;

    public static function svg(string $url): string
    {
        return (new Builder())->build(
            writer: new SvgWriter(),
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: self::SIZE,
            margin: 0,
        )->getString();
    }
}
