<?php

namespace App\Discipline;

/** Ce sur quoi un cumul de cartons est compté, toujours pour un licencié et une section (S3). */
enum TallyScope: string
{
    case Game = 'GAME';
    case Gameday = 'GAMEDAY';
    case Season = 'SEASON';

    public function keyOf(Card $card): string
    {
        return match ($this) {
            self::Game => 'game:' . $card->matchId,
            self::Gameday => 'day:' . $card->gamedayKey,
            self::Season => 'season',
        };
    }
}
