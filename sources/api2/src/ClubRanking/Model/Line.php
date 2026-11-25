<?php

namespace App\ClubRanking\Model;

/**
 * One award of points (§ 2 « ligne de détail »): which club, which rule, why, and the source object (team, licensee,
 * event, ranking) so that every point can be justified.
 */
final class Line
{
    public const SOURCE_TEAM = 'team';
    public const SOURCE_LICENSEE = 'licensee';
    public const SOURCE_EVENT = 'event';
    public const SOURCE_ENTRY = 'entry';

    public function __construct(
        public readonly string $ruleCode,
        public readonly string $clubCode,
        public readonly int $points,
        public readonly string $reason,
        public readonly string $sourceType,
        public readonly string $sourceId,
        public readonly ?int $matric = null,
    ) {
    }

    /** Identity of the award: the same rule never pays twice the same club for the same source. */
    public function key(): string
    {
        return implode('|', [$this->ruleCode, $this->clubCode, $this->sourceType, $this->sourceId]);
    }
}
