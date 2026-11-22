<?php

namespace App\Designation;

/**
 * Type de périmètre d'une Désignation (FEATURE_DESIGNATIONS.md D9, D16). `Niveau` = toutes les compétitions
 * nationales de la saison (bureau et membres de la CNA).
 */
enum ScopeType: string
{
    case Niveau = 'NIVEAU';
    case Competition = 'COMPETITION';
    case Equipe = 'EQUIPE';
    case Evenement = 'EVENEMENT';
    case Journee = 'JOURNEE';
}
