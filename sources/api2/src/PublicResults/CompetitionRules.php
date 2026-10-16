<?php

namespace App\PublicResults;

/**
 * Règles d'affichage des compétitions reprises du legacy, en un seul endroit (API_PUBLIC_RESULTS.md § 5).
 * Fonctions pures : testées unitairement.
 */
final class CompetitionRules
{
    /** `Code_tour` du tour final (affiché « F » dans app4). */
    public const FINAL_ROUND = 10;

    private const MEDAL_RANKS = [1, 2, 3];

    /** Titre affiché : le libellé si le titre est actif, sinon le sous-titre (kpclassements.tpl, PdfClt*.php). */
    public static function displayTitle(string $titleActive, ?string $libelle, ?string $soustitre): string
    {
        if ($titleActive === 'O' || $soustitre === null || $soustitre === '') {
            return (string) $libelle;
        }

        return $soustitre;
    }

    public static function isFinalRound(?int $round): bool
    {
        return $round === self::FINAL_ROUND;
    }

    /** Le classement général est publié : CHPT commencé, compétition terminée, ou MULTI (kpclassement.php). */
    public static function isRankingPublished(string $type, string $status): bool
    {
        return ($type === 'CHPT' && $status !== 'ATT') || $status === 'END' || $type === 'MULTI';
    }

    /** Médaille 1, 2 ou 3 d'une compétition terminée du tour final ; sinon null (§ 5.7). */
    public static function medal(string $status, bool $final, int $rank): ?int
    {
        return $status === 'END' && $final && in_array($rank, self::MEDAL_RANKS, true) ? $rank : null;
    }

    /** Rang propre au type : rang de niveau pour une coupe, rang de championnat sinon. */
    public static function rank(string $type, int $championshipRank, int $levelRank): int
    {
        return $type === 'CP' ? $levelRank : $championshipRank;
    }

    /** « NOM Prénom (123456) » → « NOM Prénom » : le numéro de licence n'est jamais publié (utyGetNomPrenom). */
    public static function personName(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return explode(' (', $value)[0];
    }

    /** Chemin d'un visuel sous /img/ (ou URL absolue), seulement s'il est actif et renseigné. */
    public static function visual(string $active, ?string $link): ?string
    {
        if ($active !== 'O' || $link === null || $link === '') {
            return null;
        }

        return str_starts_with($link, 'http') ? $link : 'logo/' . $link;
    }

    /** Points publiés stockés × 100 (demi-points possibles). */
    public static function points(int $storedPoints): int|float
    {
        return $storedPoints / 100;
    }

    /**
     * Rangs partagés en cas d'égalité (1, 1, 3) pour des valeurs déjà triées par ordre décroissant.
     *
     * @param list<int> $sortedValues
     *
     * @return list<int>
     */
    public static function sharedRanks(array $sortedValues): array
    {
        $ranks = [];
        foreach ($sortedValues as $index => $value) {
            $ranks[] = $index > 0 && $value === $sortedValues[$index - 1] ? $ranks[$index - 1] : $index + 1;
        }

        return $ranks;
    }
}
