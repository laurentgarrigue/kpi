<?php

namespace App\ClubRanking\Data;

/** A competition of the season: its ranking type, its level, whether its ranking is published and locked. */
final class Competition
{
    public const CHAMPIONSHIP = 'CHPT';
    public const LEVEL_INTERNATIONAL = 'INT';

    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $rankingType = self::CHAMPIONSHIP,
        public readonly string $level = 'NAT',
        public readonly bool $published = true,
        public readonly bool $locked = true,
    ) {
    }

    public function isChampionship(): bool
    {
        return $this->rankingType === self::CHAMPIONSHIP;
    }
}
