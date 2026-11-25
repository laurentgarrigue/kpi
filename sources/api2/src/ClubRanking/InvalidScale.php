<?php

namespace App\ClubRanking;

/** The scale of the season cannot be computed (§ 3.3 « barème valide », CNC-03): every configuration error. */
final class InvalidScale extends \RuntimeException
{
    /** @param list<string> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(implode("\n", $errors));
    }
}
