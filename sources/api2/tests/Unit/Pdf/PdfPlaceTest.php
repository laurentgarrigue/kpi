<?php

namespace App\Tests\Unit\Pdf;

use App\Pdf\PdfPlace;
use PHPUnit\Framework\TestCase;

final class PdfPlaceTest extends TestCase
{
    public function testPlaceWithItsDepartmentInParentheses(): void
    {
        self::assertSame('Lacville (33)', PdfPlace::label('Lacville', '33'), 'the closing parenthesis is kept');
        self::assertSame('Lacville', PdfPlace::label('Lacville', ''));
        self::assertSame('Lacville', PdfPlace::label('Lacville', null));
        self::assertSame('', PdfPlace::label(null, null));
    }
}
