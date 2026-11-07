<?php

namespace App\Journal;

/**
 * Catégories du journal d'activité (PAGE_GESTION_JOURNAL.md § 5.2) : regroupe les libellés libres de
 * `kp_journal.Actions` (écrits par le legacy et par api2) en quelques familles filtrables.
 *
 * Les règles sont des motifs SQL `LIKE` évalués dans l'ordre : la première catégorie dont un motif
 * correspond l'emporte, et une action qu'aucun motif ne reconnaît relève de {@see self::FALLBACK}
 * (la gestion sportive courante : compétitions, journées, matchs, équipes, classements, événements).
 * Les mêmes motifs servent au filtre SQL ({@see self::sqlCase()}) et au classement PHP
 * ({@see self::categoryOf()}) : la colonne est en `utf8mb3_general_ci`, d'où la comparaison
 * insensible à la casse côté PHP.
 */
final class JournalCategories
{
    public const CONNEXION = 'connexion';
    public const ADMINISTRATION = 'administration';
    public const OPERATION = 'operation';
    public const PRESENCE = 'presence';
    public const SCORING = 'scoring';
    public const PDF = 'pdf';
    public const EDITORIAL = 'editorial';

    /** Ordre d'affichage des catégories (filtre et groupes du select des actions). */
    public const ALL = [
        self::CONNEXION,
        self::ADMINISTRATION,
        self::OPERATION,
        self::PRESENCE,
        self::SCORING,
        self::PDF,
        self::EDITORIAL,
    ];

    /** Décochées par défaut : le scoring écrit une ligne par but, carton ou top chrono et noie le journal. */
    public const HIDDEN_BY_DEFAULT = [self::SCORING];

    /** Catégorie des actions qu'aucune règle ne reconnaît. */
    public const FALLBACK = self::OPERATION;

    /**
     * Motifs `LIKE` par catégorie, par ordre de priorité (la première correspondance l'emporte).
     *
     * @var array<string, list<string>>
     */
    private const RULES = [
        self::SCORING => ['Scoring %', 'Incrustation %'],
        self::PDF => ['PDF %'],
        self::CONNEXION => ['Connexion%', 'Révocation appareil', 'Lien Google%', 'Reset mot de passe'],
        self::PRESENCE => [
            '%titulaire%',
            'Copie compo%',
            '%kp_competition_equipe_joueur',
            '%kp_match_joueur',
            'Ajout joueur%',
            'Ajout coureur',
            'Suppression joueurs match',
            'Scrut %',
        ],
        self::EDITORIAL => [
            '% média',
            '% formulaire',
            'Statut inscription',
            '% article',
            '% page',
            '% menu éditorial',
            'Modification accueil',
            '% redirection',
            '% capacité Rédacteur',
        ],
        self::ADMINISTRATION => [
            '%utilisateur%',
            '% mandat',
            '%saison%',
            '%club%',
            'CD_CREATE',
            'Ajout CD',
            'ATHLETE_%',
            '% Rc',
            'Copie RC',
            '%image%',
            '%logo%',
            'Fusion %',
            'Change code',
            'Import PCE',
            'Purge Cache',
            'Update Locks',
        ],
    ];

    /**
     * Catégories valides parmi une saisie libre (paramètre `categories=a,b`), dans l'ordre d'affichage.
     * Retourne une liste vide quand rien n'est filtré : saisie vide, inconnue ou couvrant toutes les catégories.
     *
     * @return list<string>
     */
    public static function parseFilter(string $raw): array
    {
        $requested = array_map('trim', explode(',', $raw));
        $selected = array_values(array_intersect(self::ALL, $requested));

        return count($selected) === count(self::ALL) ? [] : $selected;
    }

    public static function categoryOf(string $action): string
    {
        foreach (self::RULES as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if (self::matchesLike($action, $pattern)) {
                    return $category;
                }
            }
        }

        return self::FALLBACK;
    }

    /**
     * Expression SQL `CASE` qui calcule la catégorie de `$column`. Les motifs sont des constantes de
     * cette classe, cités par `$quote` (Connection::quote) : aucune donnée utilisateur n'y entre.
     *
     * @param callable(string): string $quote
     */
    public static function sqlCase(string $column, callable $quote): string
    {
        $branches = [];
        foreach (self::RULES as $category => $patterns) {
            $tests = array_map(fn (string $pattern) => sprintf('%s LIKE %s', $column, $quote($pattern)), $patterns);
            $branches[] = sprintf('WHEN %s THEN %s', implode(' OR ', $tests), $quote($category));
        }

        return sprintf('(CASE %s ELSE %s END)', implode(' ', $branches), $quote(self::FALLBACK));
    }

    /** Sémantique de `LIKE` sous une collation `_ci` : `%` = n caractères, `_` = un caractère, casse ignorée. */
    private static function matchesLike(string $value, string $pattern): bool
    {
        $regex = '';
        foreach (mb_str_split($pattern) as $char) {
            $regex .= match ($char) {
                '%' => '.*',
                '_' => '.',
                default => preg_quote($char, '/'),
            };
        }

        return preg_match('/^' . $regex . '$/iu', $value) === 1;
    }
}
