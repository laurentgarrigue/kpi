<?php

namespace App\MatchMedals\Stats;

/** Motifs des cartons · Card Reasons: cards of the games officiated by recorded reason, with the share recorded. */
final class RefereeCardReasonsStat extends AbstractRefereeStat
{
    public function code(): string
    {
        return 'referee_card_reasons';
    }

    public function columns(): array
    {
        return [['key' => 'games', 'type' => 'integer'], ['key' => 'recorded', 'type' => 'percent']];
    }

    public function compute(MedalStatQuery $q): array
    {
        $reasons = function (array $games) use ($q): array {
            $cards = array_merge(...array_map(fn (array $g): array => self::cardsOf($q, $g['id']), array_values($games)));
            $motifs = array_values(array_filter(array_column($cards, 'motif')));

            return ['reasons' => array_count_values($motifs), 'recorded' => self::percent(count($motifs), count($cards))];
        };

        return [
            'rows' => $this->refereeRows($q, static fn (array $officiated): array => $reasons(array_column($officiated, 'game'))),
            'summary' => $reasons(self::refereedGames($q)),
        ];
    }
}
