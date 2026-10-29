<?php

namespace App\Pdf\Games;

/** Orders of the game list, those of the Games page of app4 (`sort` option). */
enum GamesSort: string
{
    case Date = 'date';
    case Competition = 'competition';
    case Phase = 'phase';
    case Pitch = 'pitch';
    case Number = 'number';

    public function orderBy(): string
    {
        return match ($this) {
            self::Date => 'm.Date_match, j.Lieu, m.Heure_match, m.Terrain + 0, m.Terrain, m.Numero_ordre',
            self::Competition => 'j.Code_competition, m.Date_match, m.Heure_match, m.Terrain + 0, m.Numero_ordre',
            self::Phase => 'j.Code_competition, j.Niveau, j.Phase, m.Heure_match, m.Terrain + 0, m.Numero_ordre',
            self::Pitch => 'm.Terrain + 0, m.Terrain, m.Date_match, m.Heure_match, m.Numero_ordre',
            self::Number => 'm.Numero_ordre, m.Date_match, m.Heure_match, m.Terrain + 0',
        };
    }
}
