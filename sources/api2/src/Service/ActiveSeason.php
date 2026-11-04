<?php

namespace App\Service;

use Doctrine\DBAL\Connection;

/**
 * The active season (kp_saison.Etat = 'A'). Between two seasons none may be active: the most recent season is then
 * used, so that what depends on it (birth date plausibility, referees pool categories) never runs on an empty season.
 */
final class ActiveSeason
{
    private const ACTIVE = 'A';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function code(): string
    {
        return (string) ($this->connection->fetchOne(
            'SELECT Code FROM kp_saison WHERE Etat = ? ORDER BY Code DESC LIMIT 1',
            [self::ACTIVE]
        ) ?: $this->connection->fetchOne('SELECT MAX(Code) FROM kp_saison'));
    }
}
