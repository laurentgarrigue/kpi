<?php

namespace App\ClubRanking\Model;

/**
 * One place that earns the points of a RANKING rule (§ 4.4): « 2e de championnat de France / vainqueur de la Coupe de
 * France » is two slots of the same rule (place 2 of U15, U18, play-offs; place 1 of CF15, CFD, CFH).
 */
final class RankingSlot
{
    public const FINAL = 'final';
    public const REGULAR = 'regular';

    /** @param list<string> $categories */
    public function __construct(
        public readonly array $categories,
        public readonly string $ranking,
        public readonly int $place,
    ) {
    }

    /** @return list<self> */
    public static function fromRule(Rule $rule): array
    {
        $slots = [];
        foreach ($rule->params['slots'] ?? [] as $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $slots[] = new self(
                array_values(array_map('strval', (array) ($slot['categories'] ?? []))),
                ($slot['ranking'] ?? self::FINAL) === self::REGULAR ? self::REGULAR : self::FINAL,
                (int) ($slot['place'] ?? 0),
            );
        }

        return $slots;
    }
}
