<?php

namespace App\MatchMedals;

use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\DataLevel;
use App\MatchMedals\Model\GameRecord;
use App\MatchMedals\Rule\MedalRule;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Applies every rule of the catalogue to one game (§ 6.1). Pure: the caller loads the game and stores the result. */
final class MatchMedalCalculator
{
    /** @var list<MedalRule> */
    private array $rules;

    /** @param iterable<MedalRule> $rules */
    public function __construct(#[AutowireIterator(MedalRule::TAG)] iterable $rules)
    {
        $this->rules = [...$rules];
    }

    /** @param list<string> $retired codes no longer computed (§ 3.1) */
    public function compute(GameRecord $game, MedalThresholds $thresholds, array $retired = []): MatchMedals
    {
        $timeline = MatchTimeline::analyse($game, $thresholds);
        $awards = [];
        if ($timeline->isEligible()) {
            foreach ($this->rules as $rule) {
                if (in_array($rule->code(), $retired, true) || ($rule->dataLevel() === DataLevel::Timeline && !$timeline->isReliable())) {
                    continue;
                }
                array_push($awards, ...$rule->award($timeline));
            }
        }

        return new MatchMedals($timeline, $awards);
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_map(static fn (MedalRule $r): string => $r->code(), $this->rules);
    }

    /** @param list<Award|null> $awards */
    public static function present(array $awards): array
    {
        return array_values(array_filter($awards));
    }
}
