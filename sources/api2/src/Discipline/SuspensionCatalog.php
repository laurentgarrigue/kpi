<?php

namespace App\Discipline;

/**
 * Catalogue des cumuls de cartons et des suspensions de l'art. 50 du règlement (« Cartons et suspensions »),
 * versionné par saison (ADR-0004, FEATURE_CARD_SUSPENSIONS.md § 2, S1, S2, S4).
 *
 * Changer un seuil, c'est ajouter une version qui commence à une saison, jamais modifier une version déjà appliquée :
 * les suspensions enregistrées gardent le numéro de la version qui les a détectées.
 *
 * Tests : tests/Unit/Discipline/SuspensionCatalogTest.php et SuspensionDetectorTest.php.
 */
final class SuspensionCatalog
{
    /** Sections de compétition (`kp_groupe.section`) couvertes : Nationale seulement (S4, décision du 2026-11-26). */
    private const COVERED_SECTIONS = [2];

    /**
     * @param list<int>            $sections
     * @param list<SuspensionRule> $rules    dans l'ordre de départage d'une égalité (S8)
     */
    private function __construct(
        public readonly int $version,
        public readonly string $firstSeason,
        public readonly array $sections,
        public readonly array $rules,
    ) {
    }

    /** Version en vigueur pour la saison, ou null avant la première saison couverte (S2). */
    public static function forSeason(string $season): ?self
    {
        $current = null;
        foreach (self::versions() as $version) {
            if ((int) $season >= (int) $version->firstSeason) {
                $current = $version;
            }
        }

        return $current;
    }

    public function coversSection(int $section): bool
    {
        return in_array($section, $this->sections, true);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'version' => $this->version,
            'firstSeason' => $this->firstSeason,
            'sections' => $this->sections,
            'rules' => array_map(static fn (SuspensionRule $rule): array => $rule->toArray(), $this->rules),
        ];
    }

    /** @return list<self> par saison de début croissante */
    private static function versions(): array
    {
        return [self::version1()];
    }

    /** Annexes 2026 au règlement sportif, appliquées à partir de la saison 2027 (seuils antérieurs : 12 verts, 6 jaunes). */
    private static function version1(): self
    {
        $yellowRed = [CardColor::Yellow, CardColor::Red];

        return new self(1, '2027', self::COVERED_SECTIONS, [
            new SuspensionRule('GREEN_SEASON', '8 cartons verts dans la saison', '8 green cards in the season',
                [CardColor::Green], TallyScope::Season, 8, Repeat::EveryMultiple, 1, false),
            new SuspensionRule('YELLOW_RED_GAMEDAY', "2 cartons jaunes ou rouges dans des matchs différents d'une journée", '2 yellow or red cards in different games of a gameday',
                $yellowRed, TallyScope::Gameday, 2, Repeat::EveryCardAfter, 1, false, distinctGames: true),
            new SuspensionRule('YELLOW_RED_SEASON', '4 cartons jaunes ou rouges dans la saison', '4 yellow or red cards in the season',
                $yellowRed, TallyScope::Season, 4, Repeat::EveryMultiple, 1, false),
            new SuspensionRule('BLACK_GAME', '1 carton noir dans un match', '1 black card in a game',
                [CardColor::Black], TallyScope::Game, 1, Repeat::EveryCardAfter, 2, true, gamedayTotal: true),
            new SuspensionRule('BLACK_GAMEDAY', '2 cartons noirs dans une journée', '2 black cards in a gameday',
                [CardColor::Black], TallyScope::Gameday, 2, Repeat::EveryCardAfter, 3, true, gamedayTotal: true),
            new SuspensionRule('BLACK_SEASON', '3 cartons noirs dans la saison', '3 black cards in the season',
                [CardColor::Black], TallyScope::Season, 3, Repeat::EveryCardAfter, 4, true, gamedayTotal: true),
        ]);
    }
}
