<?php

namespace App\Report;

/** Rattachement d'un rapport officiel (FEATURE_DESIGNATIONS.md D12, D14). */
enum ReportScope: string
{
    case Competition = 'COMPETITION';
    case Evenement = 'EVENEMENT';
    case Journee = 'JOURNEE';
    case Match = 'MATCH';
}
