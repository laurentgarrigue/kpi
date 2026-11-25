<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use App\ClubRanking\Model\Scale;

/**
 * « Club organisateur d'… » (7.2.1 to 7.2.4, 7.2.6): one unit per organised event whose nature is the `nature` of the
 * rule (§ 5.7, I-12 to I-14). An event earns a single rule, the highest that applies.
 */
final class EventOrganiserCalculator implements RuleCalculator
{
    public function type(): RuleType
    {
        return RuleType::EventOrganiser;
    }

    public function validate(Rule $rule, Scale $scale): array
    {
        return in_array($rule->params['nature'] ?? null, OrganisedEvent::NATURES, true)
            ? []
            : [sprintf('%s : nature d\'événement attendue parmi %s.', $rule->code(), implode(', ', OrganisedEvent::NATURES))];
    }

    public function compute(Rule $rule, CalculationContext $context): CalculationResult
    {
        $events = new OrganisedEvents($context);
        $lines = [];
        foreach ($events->events() as $event) {
            if ($event->nature === $rule->params['nature']) {
                $lines[] = new Line($rule->code(), $event->organiser(), $rule->points, $event->describe(), Line::SOURCE_EVENT, $event->id());
            }
        }

        return new CalculationResult($lines, $events->alerts());
    }
}
