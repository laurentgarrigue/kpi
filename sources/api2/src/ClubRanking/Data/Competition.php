<?php

namespace App\ClubRanking\Data;

/** A competition of the season: its ranking type, its section (kp_groupe), whether its ranking is published and locked. */
final class Competition
{
    public const CHAMPIONSHIP = 'CHPT';
    public const TOURNAMENT = 'CP';
    /** Section « International » of the competitions page (kp_groupe.section): not the level INT, which tournaments also carry. */
    public const SECTION_INTERNATIONAL = 1;
    public const SECTION_NATIONAL = 2;

    public function __construct(
        public readonly string $code,
        public readonly string $label,
        public readonly string $rankingType = self::CHAMPIONSHIP,
        public readonly int $section = self::SECTION_NATIONAL,
        public readonly bool $published = true,
        public readonly bool $locked = true,
    ) {
    }

    public function isInternational(): bool
    {
        return $this->section === self::SECTION_INTERNATIONAL;
    }

    public function isTournament(): bool
    {
        return $this->rankingType === self::TOURNAMENT;
    }

    public function isChampionship(): bool
    {
        return $this->rankingType === self::CHAMPIONSHIP;
    }
}
