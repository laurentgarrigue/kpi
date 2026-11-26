<?php

namespace App\Discipline;

use App\RuleCatalog\RuleCatalogDescriber;
use App\RuleCatalog\RuleCatalogDescription;
use App\RuleCatalog\RuleSection;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/** The version of SuspensionCatalog in force for a season, for the `rules-catalog` PDF (FEATURE_RULES_CATALOG_PDF.md § 4.1). */
#[AsTaggedItem(priority: 35)]
final class SuspensionCatalogDescriber implements RuleCatalogDescriber
{
    public const CODE = 'card-suspensions';

    private const TITLE = 'Cumuls de cartons et suspensions';
    private const SCOPES = [TallyScope::Game->value => 'match', TallyScope::Gameday->value => 'journée', TallyScope::Season->value => 'saison'];
    private const REPEATS = [Repeat::EveryMultiple->value => 'à chaque multiple du seuil', Repeat::EveryCardAfter->value => 'à chaque carton en plus'];
    private const SOURCES = ['sources/api2/src/Discipline/SuspensionCatalog.php', 'DOC/specs/FEATURE_CARD_SUSPENSIONS.md'];

    public function code(): string
    {
        return self::CODE;
    }

    public function describe(string $season): RuleCatalogDescription
    {
        $catalog = SuspensionCatalog::forSeason($season);
        if ($catalog === null) {
            return new RuleCatalogDescription(self::TITLE, null, self::SOURCES, [
                RuleSection::lines('Version en vigueur', [sprintf('Aucune version du catalogue ne couvre la saison %s.', $season)]),
            ]);
        }

        return new RuleCatalogDescription(
            title: self::TITLE,
            version: sprintf('v%d (depuis la saison %s)', $catalog->version, $catalog->firstSeason),
            sources: self::SOURCES,
            sections: [
                RuleSection::pairs('Champ d\'application', [
                    'Sections couvertes' => RuleSection::list(array_map(
                        static fn (int $section): string => SuspensionMessage::SECTION_LABELS[$section] ?? (string) $section,
                        $catalog->sections,
                    )),
                    'Égalité entre règles' => 'la première règle du tableau l\'emporte',
                ]),
                RuleSection::table(
                    'Règles (dans l\'ordre de départage)',
                    [
                        'code' => 'Code', 'label' => 'Règle', 'cards' => 'Cartons comptés', 'scope' => 'Compté sur',
                        'threshold' => 'Seuil', 'repeat' => 'Redéclenchement', 'matches' => 'Matchs de suspension', 'jury' => 'Jury d\'appel',
                    ],
                    array_map(static fn (SuspensionRule $rule): array => [
                        'code' => $rule->code,
                        'label' => $rule->labelFr,
                        'cards' => RuleSection::list(array_map(static fn (CardColor $color): string => $color->labelFr(), $rule->cards)),
                        'scope' => self::SCOPES[$rule->scope->value] . ($rule->distinctGames ? ' (matchs différents)' : ''),
                        'threshold' => $rule->threshold,
                        'repeat' => self::REPEATS[$rule->repeat->value] . ($rule->gamedayTotal ? ' ; la journée vaut la plus lourde' : ''),
                        'matches' => $rule->matches,
                        'jury' => RuleSection::yesNo($rule->appealJury),
                    ], $catalog->rules),
                ),
            ],
        );
    }
}
