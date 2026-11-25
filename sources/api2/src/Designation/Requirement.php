<?php

namespace App\Designation;

/** Exigence d'un rôle sur un périmètre, pour le suivi de couverture (FEATURE_DESIGNATIONS.md D26). */
enum Requirement: string
{
    /** Non pourvu → case rouge. */
    case Required = 'required';
    case Optional = 'optional';
    /** Le rôle n'a pas de sens à ce niveau (ex. délégué CNA en régionale). */
    case NotApplicable = 'not_applicable';
}
