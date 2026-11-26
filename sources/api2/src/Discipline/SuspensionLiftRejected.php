<?php

namespace App\Discipline;

/** Levée refusée (FEATURE_CARD_SUSPENSIONS.md S19) : suspension inconnue (404), annulée ou déjà levée (409). */
final class SuspensionLiftRejected extends \RuntimeException
{
    public function __construct(public readonly string $reason, public readonly int $status)
    {
        parent::__construct($reason);
    }
}
