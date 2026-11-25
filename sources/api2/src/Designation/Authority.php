<?php

namespace App\Designation;

/**
 * Qui a décidé une Désignation (FEATURE_DESIGNATIONS.md D23). Pour un arbitre non-joueur, `Organisateur` ouvre une
 * rétribution au club fournisseur ; `Cna` (pool arbitres) relève du budget CNA.
 */
enum Authority: string
{
    case Cna = 'CNA';
    case Organisateur = 'ORGANISATEUR';
}
