<?php

namespace App\ClubRanking\Data;

/** A licensee as read at the reference date (§ 5.1): current club, last licensed season, foreign or not. */
final class Licensee
{
    public function __construct(
        public readonly int $matric,
        public readonly string $lastName,
        public readonly string $firstName,
        public readonly string $clubCode,
        public readonly string $lastSeason,
        public readonly bool $foreign = false,
    ) {
    }

    public function isLicensedFor(string $season): bool
    {
        return ctype_digit($this->lastSeason) && $this->lastSeason >= $season;
    }

    public function name(): string
    {
        return trim($this->lastName . ' ' . $this->firstName);
    }
}
