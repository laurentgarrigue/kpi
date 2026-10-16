<?php

namespace App\PublicResults;

use App\PublicSite\MediaFiles;

/**
 * Logos des équipes des résultats publics. La colonne `kp_competition_equipe.logo` n'est pas toujours renseignée
 * (souvent vide dans une compétition ajoutée en cours de saison, alors que la même équipe a son logo ailleurs) :
 * une ligne sans logo prend celui du club, sinon le drapeau de sa nation (MediaFiles::clubLogo), sinon le logo vide.
 */
final class TeamLogos
{
    public const EMPTY_LOGO = 'KIP/logo/empty-logo.png';

    public function __construct(private readonly MediaFiles $media)
    {
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param string                     ...$prefixes préfixes des colonnes d'équipe : `t_`, `t_a_`, `t_b_`
     *
     * @return list<array<string, mixed>>
     */
    public function fill(array $rows, string ...$prefixes): array
    {
        $byClub = [];
        foreach ($rows as &$row) {
            foreach ($prefixes as $prefix) {
                $logo = $row[$prefix . 'logo'] ?? null;
                $club = $row[$prefix . 'club'] ?? null;
                if (($logo !== null && $logo !== '' && $logo !== self::EMPTY_LOGO) || $club === null || $club === '') {
                    continue;
                }
                $byClub[$club] ??= $this->media->clubLogo($club) ?? self::EMPTY_LOGO;
                $row[$prefix . 'logo'] = $byClub[$club];
            }
        }
        unset($row);

        return $rows;
    }
}
