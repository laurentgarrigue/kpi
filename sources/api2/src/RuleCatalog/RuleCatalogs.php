<?php

namespace App\RuleCatalog;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** Every described catalogue, in the order of the document (FEATURE_RULES_CATALOG_PDF.md § 2). */
class RuleCatalogs
{
    /** @var array<string, RuleCatalogDescriber> */
    private array $describers = [];

    /** @param iterable<RuleCatalogDescriber> $describers */
    public function __construct(#[AutowireIterator(RuleCatalogDescriber::TAG)] iterable $describers)
    {
        foreach ($describers as $describer) {
            if (isset($this->describers[$describer->code()])) {
                throw new \LogicException(sprintf('Two rule catalogues share the code "%s".', $describer->code()));
            }
            $this->describers[$describer->code()] = $describer;
        }
    }

    /** @return array<string, RuleCatalogDescriber> by code, in the order of the document */
    public function all(): array
    {
        return $this->describers;
    }

    public function find(string $code): ?RuleCatalogDescriber
    {
        return $this->describers[$code] ?? null;
    }
}
