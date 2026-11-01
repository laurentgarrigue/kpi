<?php

namespace App\Pdf;

/** Place of a gameday on the documents: « Lacville (33) », or the place alone without department. */
final class PdfPlace
{
    public static function label(?string $place, ?string $department): string
    {
        $place = trim((string) $place);
        $department = trim((string) $department);

        return $department === '' ? $place : trim(sprintf('%s (%s)', $place, $department));
    }
}
