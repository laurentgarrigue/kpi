<?php

namespace App\MatchMedals\Model;

/**
 * Everything the medal rules need about one game (FEATURE_MATCH_MEDALS.md § 5): its result, its timeline and its
 * designated referees. Built by the repository from kp_match, kp_match_detail and kp_match_joueur.
 */
final readonly class GameRecord
{
    public const TYPE_KNOCKOUT = 'E';

    /**
     * @param list<GameEvent> $events in recording order (date_insert, Id)
     * @param list<Referee>   $referees designated referees only (licence number > 0)
     */
    public function __construct(
        public int $id,
        public string $type,
        public ?string $finalPeriod,
        public ?string $scoreA,
        public ?string $scoreB,
        public bool $isValidated,
        public bool $isPublished,
        public bool $hasTwoTeams,
        public array $events,
        public array $referees,
    ) {
    }
}
