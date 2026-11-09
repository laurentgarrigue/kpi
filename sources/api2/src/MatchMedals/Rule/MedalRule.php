<?php

namespace App\MatchMedals\Rule;

use App\MatchMedals\MatchTimeline;
use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * One element of the catalogue (FEATURE_MATCH_MEDALS.md § 6.1): a medal or a game label. Adding one = a class
 * implementing this interface, its catalogue row (migration) and its line in the spec; nothing else changes.
 */
#[AutoconfigureTag(self::TAG)]
interface MedalRule
{
    public const TAG = 'app.match_medal_rule';

    /** Stable code, key of kp_medal_catalog. */
    public function code(): string;

    /** A Timeline rule is only applied to a game whose timeline is reliable (§ 5.1). */
    public function dataLevel(): DataLevel;

    /** @return list<Award> */
    public function award(MatchTimeline $game): array;
}
