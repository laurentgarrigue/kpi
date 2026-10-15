<?php

namespace App\Forms;

/**
 * Envoi refusé pour une raison globale (pas un champ) : `not_found`, `closed`, `full`, `rejected` (robot),
 * `invalid` / `too_fast` / `expired` (jeton).
 */
final class FormRejected extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }
}
