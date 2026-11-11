<?php

namespace App\MatchMedals\Model;

/** A referee designated by licence number (Matric_arbitre_principal / _secondaire > 0). */
final readonly class Referee
{
    public const PRINCIPAL = 'principal';
    public const SECONDARY = 'secondaire';

    public function __construct(public int $matric, public string $role)
    {
    }
}
