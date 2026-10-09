<?php

namespace App\PublicResults\Dto;

/**
 * Ligne de classement (§ 5.2 compact, § 5.3 général). Les colonnes de détail ne sont présentes que dans le
 * classement général d'un CHPT ou d'une CP.
 */
final class RankingRow implements \JsonSerializable
{
    /** @param array{won: int, drawn: int, lost: int, forfeits: int, goals_for: int, goals_against: int, goal_diff: int}|null $details */
    public function __construct(
        public readonly int $rank,
        public readonly TeamRef $team,
        public readonly int|float $points,
        public readonly int $played,
        public readonly ?int $medal,
        public readonly ?array $details = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'rank' => $this->rank,
            'team' => $this->team,
            'points' => $this->points,
            'played' => $this->played,
            ...($this->details ?? []),
            'medal' => $this->medal,
        ];
    }
}
