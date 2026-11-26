<?php

namespace App\MatchMedals\Catalog;

use App\RuleCatalog\RuleCatalogDescriber;
use App\RuleCatalog\RuleCatalogDescription;
use App\RuleCatalog\RuleSection;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The medals of a season for the `rules-catalog` PDF (FEATURE_RULES_CATALOG_PDF.md § 4.1): the thresholds the season
 * was computed with (the ones in force for a season never computed) and the active elements of the catalogue, their
 * explanation rendered with those thresholds.
 */
#[AsTaggedItem(priority: 30)]
final class MedalCatalogDescriber implements RuleCatalogDescriber
{
    public const CODE = 'medals';

    public function __construct(
        private readonly MedalCatalog $catalog,
        #[Autowire('%match_medals.rules_version%')] private readonly int $rulesVersion,
    ) {
    }

    public function code(): string
    {
        return self::CODE;
    }

    public function describe(string $season): RuleCatalogDescription
    {
        $thresholds = $this->catalog->thresholdsOf($season);
        $computed = in_array($season, $this->catalog->computedSeasons(), true);

        return new RuleCatalogDescription(
            title: 'Médailles et seuils',
            version: sprintf('règles v%d', $this->rulesVersion),
            sources: [
                'sources/api2/src/MatchMedals/Catalog/MedalCatalog.php',
                'sources/api2/config/packages/match_medals.yaml',
                'tables kp_medal_catalog, kp_medal_setting, kp_medal_season',
                'DOC/specs/public/FEATURE_MATCH_MEDALS.md',
            ],
            sections: [
                RuleSection::table(
                    $computed ? 'Seuils figés au calcul de la saison' : 'Seuils en vigueur (saison pas encore calculée)',
                    ['name' => 'Seuil', 'value' => 'Valeur'],
                    array_map(static fn (string $name, int|array $value): array => [
                        'name' => $name,
                        'value' => is_array($value) ? RuleSection::list($value) : (string) $value,
                    ], array_keys($thresholds->toArray()), $thresholds->toArray()),
                ),
                RuleSection::table(
                    'Éléments actifs du catalogue',
                    ['code' => 'Code', 'name' => 'Nom', 'type' => 'Type', 'family' => 'Famille', 'explanation' => 'Explication'],
                    array_map(static fn (array $row): array => [
                        'code' => $row['code'],
                        'name' => $row['name_fr'],
                        'type' => $row['type'],
                        'family' => $row['family'],
                        'explanation' => MedalCatalog::render($row['explanation_fr'], $thresholds),
                    ], $this->catalog->visible(MedalSurface::Admin)),
                ),
            ],
        );
    }
}
