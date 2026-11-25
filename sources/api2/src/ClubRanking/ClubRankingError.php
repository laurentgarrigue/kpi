<?php

namespace App\ClubRanking;

/** A refused operation on the club ranking, with the HTTP status to answer and the detail of the errors. */
final class ClubRankingError extends \RuntimeException
{
    /** @param list<string> $errors */
    public function __construct(string $message, public readonly int $status = 422, public readonly array $errors = [])
    {
        parent::__construct($message);
    }
}
