<?php

namespace App\RuleCatalog;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Readable description of the values of one business rule catalogue (FEATURE_RULES_CATALOG_PDF.md § 3), for the
 * `rules-catalog` PDF. A describer lives next to its catalogue and reads it: it never copies a value of the rule.
 * The priority of the tag (`#[AsTaggedItem]`) gives the order of the catalogues in the document.
 */
#[AutoconfigureTag(self::TAG)]
interface RuleCatalogDescriber
{
    public const TAG = 'app.rule_catalog_describer';

    /** Stable id of the catalogue, the `catalog` option of the document, e.g. `scoring`. */
    public function code(): string;

    /** Values in force for a season (YYYY), in French (the language of the regulation). */
    public function describe(string $season): RuleCatalogDescription;
}
