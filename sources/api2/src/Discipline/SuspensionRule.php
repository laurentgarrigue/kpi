<?php

namespace App\Discipline;

/** Une règle de l'art. 50 : quels cartons, comptés sur quoi, à partir de combien, pour quelle suspension. */
final class SuspensionRule
{
    /**
     * @param list<CardColor> $cards         couleurs comptées
     * @param bool            $distinctGames le seuil compte des matchs différents, pas des cartons (« 2 jaunes ou
     *                                       rouges dans des matchs différents d'une journée »)
     */
    public function __construct(
        public readonly string $code,
        public readonly string $labelFr,
        public readonly string $labelEn,
        public readonly array $cards,
        public readonly TallyScope $scope,
        public readonly int $threshold,
        public readonly Repeat $repeat,
        public readonly int $matches,
        public readonly bool $appealJury,
        public readonly bool $distinctGames = false,
    ) {
    }

    public function counts(CardColor $color): bool
    {
        return in_array($color, $this->cards, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'labelFr' => $this->labelFr,
            'labelEn' => $this->labelEn,
            'cards' => array_map(static fn (CardColor $color): string => $color->value, $this->cards),
            'scope' => $this->scope->value,
            'threshold' => $this->threshold,
            'repeat' => $this->repeat->value,
            'distinctGames' => $this->distinctGames,
            'matches' => $this->matches,
            'appealJury' => $this->appealJury,
        ];
    }
}
