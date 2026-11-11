<?php

namespace App\MatchMedals;

use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\AwardScope;

/** Result of one game: its analysed timeline and the awards it gives. */
final readonly class MatchMedals
{
    /** @param list<Award> $awards */
    public function __construct(public MatchTimeline $timeline, public array $awards)
    {
    }

    /** @return list<Award> */
    public function of(AwardScope $scope): array
    {
        return array_values(array_filter($this->awards, static fn (Award $a): bool => $a->scope === $scope));
    }

    /** @return list<string> codes of the game labels */
    public function labels(): array
    {
        return array_map(static fn (Award $a): string => $a->code, $this->of(AwardScope::Game));
    }
}
