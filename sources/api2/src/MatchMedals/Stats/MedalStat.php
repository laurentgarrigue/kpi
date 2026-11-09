<?php

namespace App\MatchMedals\Stats;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A timeline statistic of the catalogue (FEATURE_MATCH_MEDALS.md § 3.4, § 3.5). Adding one = a class implementing
 * this interface and its catalogue row; the endpoints and the generic displays pick it up.
 */
#[AutoconfigureTag(self::TAG)]
interface MedalStat
{
    public const TAG = 'app.match_medal_stat';

    /** Code, key of kp_medal_catalog and kind of the stats endpoints. */
    public function code(): string;

    /** Timeline statistics only count the games whose timeline is reliable (§ 3.4). */
    public function needsReliableTimeline(): bool;

    /** @return list<array{key: string, type: string}> */
    public function columns(): array;

    /** @return array{rows: list<array<string, mixed>>, summary?: array<string, mixed>} */
    public function compute(MedalStatQuery $query): array;
}
