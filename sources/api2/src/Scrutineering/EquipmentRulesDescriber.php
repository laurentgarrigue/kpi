<?php

namespace App\Scrutineering;

use App\RuleCatalog\RuleCatalogDescriber;
use App\RuleCatalog\RuleCatalogDescription;
use App\RuleCatalog\RuleSection;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/** Values of EquipmentRules for the `rules-catalog` PDF (FEATURE_RULES_CATALOG_PDF.md § 4.1). */
#[AsTaggedItem(priority: 20)]
final class EquipmentRulesDescriber implements RuleCatalogDescriber
{
    public const CODE = 'equipment';

    public function code(): string
    {
        return self::CODE;
    }

    public function describe(string $season): RuleCatalogDescription
    {
        $maximums = EquipmentRules::maximums();

        return new RuleCatalogDescription(
            title: 'Contrôle équipement',
            version: null,
            sources: ['sources/api2/src/Scrutineering/EquipmentRules.php', 'DOC/specs/PAGE_SCRUTINEERING.md'],
            sections: [
                RuleSection::table('Éléments contrôlés', ['parameter' => 'Élément', 'column' => 'Colonne', 'values' => 'Valeurs admises'], array_map(
                    static fn (string $column, int $maximum): array => [
                        'parameter' => EquipmentRules::journalLabel($column),
                        'column' => $column,
                        'values' => sprintf('0 à %d', $maximum),
                    ],
                    array_keys($maximums),
                    $maximums,
                )),
                RuleSection::table('États d\'un élément', ['value' => 'Valeur', 'label' => 'Signification'], array_map(
                    static fn (int $value, string $label): array => ['value' => $value, 'label' => $label],
                    array_keys(EquipmentRules::STATUSES),
                    EquipmentRules::STATUSES,
                )),
                RuleSection::lines('Pagaies', ['Le nombre de pagaies valides est un compte, pas un état.']),
            ],
        );
    }
}
