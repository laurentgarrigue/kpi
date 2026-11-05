<?php

namespace App\Pdf;

/** Charter of a PDF (PDF_GOTENBERG.md § 8): admin PDFs look like app4, public PDFs like the public website (app3). */
enum PdfTheme: string
{
    case Admin = 'admin';
    case Public = 'public';
}
