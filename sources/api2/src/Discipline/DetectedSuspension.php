<?php

namespace App\Discipline;

/** Suspension déclenchée par un carton (S8) : la règle la plus lourde est retenue, les autres sont listées. */
final class DetectedSuspension
{
    /**
     * @param list<SuspensionRule> $rules toutes les règles déclenchées par ce carton, dans l'ordre du catalogue
     * @param int                  $tally décompte de la règle retenue (« 4e carton jaune ou rouge de la saison »)
     */
    public function __construct(
        public readonly Card $card,
        public readonly SuspensionRule $rule,
        public readonly array $rules,
        public readonly int $tally,
        public readonly int $matches,
        public readonly bool $appealJury,
    ) {
    }

    /** @return list<string> */
    public function ruleCodes(): array
    {
        return array_map(static fn (SuspensionRule $rule): string => $rule->code, $this->rules);
    }
}
