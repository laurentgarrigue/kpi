<?php

namespace App\PublicResults\Stats;

use App\PublicResults\Scope\CompetitionScope;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Une statistique publique d'une compétition (API_PUBLIC_RESULTS.md § 5.4). Ajouter une statistique = ajouter
 * une classe implémentant cette interface : elle est enregistrée automatiquement, sans toucher au contrôleur.
 */
#[AutoconfigureTag(self::TAG)]
interface CompetitionStat
{
    public const TAG = 'app.public_competition_stat';

    /** Identifiant d'URL (`/stats/{kind}`), stable. */
    public function kind(): string;

    /** @return list<array{key: string, type: string}> valeurs propres à la statistique, pour un affichage générique */
    public function columns(): array;

    /** @return list<array<string, mixed>> lignes triées, `rank` compris */
    public function rows(CompetitionScope $competition, int $limit): array;
}
