<?php

namespace App\Designation;

/** Résultat d'une création : la Désignation et l'état du compte du licencié (MAN-02 : la réponse le signale). */
final class CreatedDesignation
{
    /** @param GeneratedMandates::ACCOUNT_* $account */
    public function __construct(
        public readonly Designation $designation,
        public readonly string $account,
    ) {
    }
}
