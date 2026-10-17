<?php

namespace App\PublicResults\Scope;

/**
 * Portée d'une requête de résultats publics : le SEUL élément qui varie entre un événement, une journée,
 * un groupe et une compétition (DOC/specs/public/API_PUBLIC_RESULTS.md § 3).
 *
 * Les fragments SQL s'appuient sur les alias communs du repository : `j` (kp_journee), `c` (kp_competition).
 */
interface ResultsScope
{
    /** Jointure supplémentaire nécessaire au filtre (placée juste après celle de kp_journee), ou ''. */
    public function join(): string;

    /** Condition SQL, avec des marqueurs `?` positionnels. */
    public function condition(): string;

    /** @return list<int|string> valeurs des marqueurs de condition(), dans l'ordre */
    public function parameters(): array;

    /**
     * Vrai quand la portée désigne explicitement UNE journée : ses phases `Break`/`Pause` ne sont alors pas
     * écartées des tableaux (comportement historique de GET /event/{id}/charts pour id >= 3000).
     */
    public function isSingleGameday(): bool;
}
