<?php

namespace App\Pdf\Games;

/** Games an audience may see (PDF_GOTENBERG.md § 7.3, PDF-AUTH-10, PDF-DATA-03). */
enum GamesVisibility
{
    /** Admin: every game of the perimeter. */
    case All;
    /** Profile 7: published games of published gamedays, competitions waiting to start (ATT) left out. */
    case Team;
    /** Public: published games of published gamedays of published competitions. */
    case Public;
}
