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
        public int $quadrupleGoals,
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

    /** Allowed range of every integer threshold: what the admin accepts (FEATURE_MATCH_MEDALS.md § 5.4, MED-67). */
    private const LIMITS = [
        'bounceBackSeconds' => [1, 600], 'comebackDeficit' => [1, 20], 'scoringRunGoals' => [2, 20], 'lateWindowSeconds' => [1, 600],
        'fastStartSeconds' => [1, 600], 'hatTrickGoals' => [2, 20], 'quadrupleGoals' => [3, 30], 'bigWinMargin' => [2, 50], 'penaltyKillExclusions' => [1, 10],
        'marathonOvertimes' => [1, 10], 'exclusionSeconds' => [10, 600], 'regularPeriodSeconds' => [60, 1800], 'extraPeriodSeconds' => [30, 900],
        'thrillerLeadChanges' => [1, 20], 'seesawEqualizers' => [1, 20], 'goalFestTopPercent' => [1, 100], 'goalFestMinGames' => [1, 1000],
        'chessMatchMaxGoals' => [1, 20], 'goalTimingSliceSeconds' => [30, 1200], 'refereeMinGames' => [1, 100],
    ];
    public const CARD_CODES = ['V', 'J', 'R', 'D'];

    /** @return array<string, array{int, int}> inclusive range of each integer threshold */
    public static function limits(): array
    {
        return self::LIMITS;
    }

    /**
     * Checks changes coming from the admin and normalises them (integers, cards in the order of CARD_CODES).
     *
     * @param array<string, mixed> $changes
     *
     * @return array<string, int|list<string>>
     *
     * @throws \InvalidArgumentException on an unknown name, a value out of range or of the wrong type
     */
    public static function validate(array $changes): array
    {
        $valid = [];
        foreach ($changes as $name => $value) {
            if ($name === 'permanentCards') {
                $cards = is_array($value) ? array_values(array_unique(array_map('strval', $value))) : null;
                if ($cards === null || array_diff($cards, self::CARD_CODES) !== []) {
                    throw new \InvalidArgumentException('Invalid permanentCards');
                }
                $valid[$name] = array_values(array_intersect(self::CARD_CODES, $cards));
                continue;
            }
            if (!isset(self::LIMITS[$name])) {
                throw new \InvalidArgumentException("Unknown threshold $name");
            }
            [$min, $max] = self::LIMITS[$name];
            if (!is_int($value) && !(is_string($value) && preg_match('/^\d+$/', $value) === 1) || (int) $value < $min || (int) $value > $max) {
                throw new \InvalidArgumentException("Invalid $name: expected an integer from $min to $max");
            }
            $valid[$name] = (int) $value;
        }

        return $valid;
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
