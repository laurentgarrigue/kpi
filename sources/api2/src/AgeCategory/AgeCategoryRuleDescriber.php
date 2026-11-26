<?php

namespace App\AgeCategory;

use App\RuleCatalog\RuleCatalogDescriber;
use App\RuleCatalog\RuleCatalogDescription;
use App\RuleCatalog\RuleSection;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/**
 * The age categories of a season for the `rules-catalog` PDF (FEATURE_RULES_CATALOG_PDF.md § 4.1): the birth years of
 * each category are found by asking the rule, year by year, never by recomputing the age.
 */
#[AsTaggedItem(priority: 50)]
final class AgeCategoryRuleDescriber implements RuleCatalogDescriber
{
    public const CODE = 'age-categories';

    public function __construct(private readonly AgeCategoryTable $table)
    {
    }

    public function code(): string
    {
        return self::CODE;
    }

    public function describe(string $season): RuleCatalogDescription
    {
        $years = $this->birthYears($season);

        return new RuleCatalogDescription(
            title: 'Catégories d\'âge',
            version: null,
            sources: ['sources/api2/src/AgeCategory/AgeCategoryRule.php', 'table kp_categorie', 'DOC/specs/CATEGORIE_AGE.md'],
            sections: [
                RuleSection::table(
                    sprintf('Catégories de la saison %s', $season),
                    ['code' => 'Code', 'label' => 'Libellé', 'ages' => 'Âges dans la saison', 'years' => 'Années de naissance'],
                    array_map(static fn (AgeCategory $category): array => [
                        'code' => $category->code,
                        'label' => $category->label,
                        'ages' => sprintf('%d à %d ans', $category->minAge, $category->maxAge),
                        'years' => isset($years[$category->code]) ? sprintf('%d–%d', min($years[$category->code]), max($years[$category->code])) : '—',
                    ], $this->table->all()),
                ),
                RuleSection::pairs('Date de naissance', [
                    'Âge retenu' => 'âge atteint dans la saison de la compétition (saison − année de naissance)',
                    'Âge plausible' => sprintf('%d à %d ans', AgeCategoryRule::MIN_PLAUSIBLE_AGE, AgeCategoryRule::MAX_PLAUSIBLE_AGE),
                    'Dates conventionnelles « inconnue »' => RuleSection::list(AgeCategoryRule::CONVENTIONAL_BIRTH_DATES),
                ]),
            ],
        );
    }

    /** @return array<string, list<int>> birth years of each category code for the season */
    private function birthYears(string $season): array
    {
        $categories = $this->table->all();
        if ($categories === []) {
            return [];
        }
        $rule = new AgeCategoryRule($this->table);
        $oldest = (int) $season - max(array_map(static fn (AgeCategory $c): int => $c->maxAge, $categories));
        $youngest = (int) $season - min(array_map(static fn (AgeCategory $c): int => $c->minAge, $categories));
        $years = [];
        for ($year = $oldest; $year <= $youngest; ++$year) {
            $category = $rule->categoryFor(sprintf('%04d-06-15', $year), $season);
            if ($category !== null) {
                $years[$category->code][] = $year;
            }
        }

        return $years;
    }
}
