<?php

namespace App\Discipline;

/** Couleur d'un carton, codée comme dans la timeline (`kp_match_detail.Id_evt_match`). */
enum CardColor: string
{
    case Green = 'V';
    case Yellow = 'J';
    case Red = 'R';
    case Black = 'D';

    public function labelFr(): string
    {
        return match ($this) {
            self::Green => 'vert',
            self::Yellow => 'jaune',
            self::Red => 'rouge',
            self::Black => 'noir',
        };
    }
}
