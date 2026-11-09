<?php

namespace App\MatchMedals;

/**
 * Every threshold of the medal rules (FEATURE_MATCH_MEDALS.md § 5.4), from config/packages/match_medals.yaml.
 * A season keeps a copy of the thresholds it was computed with (kp_medal_season), so this object is rebuilt
 * from that copy with fromArray(); no threshold is written anywhere else.
 */
final readonly class MedalThresholds
{
    /** @param list<string> $permanentCards */
    public function __construct(
        public int $bounceBackSeconds,
        public int $comebackDeficit,
        public int $scoringRunGoals,
        public int $lateWindowSeconds,
        public int $fastStartSeconds,
        public int $hatTrickGoals,
        public int $bigWinMargin,
        public int $penaltyKillExclusions,
        public int $marathonOvertimes,
        public int $exclusionSeconds,
        public array $permanentCards,
        public int $regularPeriodSeconds,
        public int $extraPeriodSeconds,
        public int $thrillerLeadChanges,
        public int $seesawEqualizers,
        public int $goalFestTopPercent,
        public int $goalFestMinGames,
        public int $chessMatchMaxGoals,
        public int $goalTimingSliceSeconds,
        public int $refereeMinGames,
    ) {
    }

    /** @param array<string, mixed> $values */
    public static function fromArray(array $values): self
    {
        $missing = array_diff(self::names(), array_keys($values));
        if ($missing !== []) {
            throw new \InvalidArgumentException('Missing medal thresholds: '.implode(', ', $missing));
        }
        $args = [];
        foreach (self::names() as $name) {
            $args[$name] = $name === 'permanentCards' ? array_values(array_map('strval', (array) $values[$name])) : (int) $values[$name];
        }

        return new self(...$args);
    }

    /** @return array<string, int|list<string>> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }

    /** @return list<string> */
    private static function names(): array
    {
        return array_map(static fn (\ReflectionParameter $p): string => $p->getName(), (new \ReflectionMethod(self::class, '__construct'))->getParameters());
    }
}
