<?php

namespace App\RuleCatalog;

/**
 * What a catalogue says of its values: sections in the shape of the generic PDF template (`sections.html.twig`):
 * a table (columns + rows), pairs of label / value, or lines of text.
 */
final class RuleCatalogDescription
{
    /**
     * @param string|null                $version version of the catalogue, null when it has none
     * @param list<string>               $sources files of the repository (and tables) holding the values
     * @param list<array<string, mixed>> $sections built with the RuleSection helpers
     */
    public function __construct(
        public readonly string $title,
        public readonly ?string $version,
        public readonly array $sources,
        public readonly array $sections,
    ) {
    }
}
