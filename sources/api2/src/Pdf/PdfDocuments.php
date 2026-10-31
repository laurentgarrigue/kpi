<?php

namespace App\Pdf;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/** The documents already migrated (PDF_GOTENBERG.md § 12): one provider per document of the catalogue. */
class PdfDocuments
{
    /** @var array<string, PdfDocumentProvider> */
    private array $providers = [];

    /** @param iterable<PdfDocumentProvider> $providers */
    public function __construct(#[AutowireIterator(PdfDocumentProvider::TAG)] iterable $providers)
    {
        foreach ($providers as $provider) {
            $this->providers[$provider->document()] = $provider;
        }
    }

    public function find(string $document, PdfTheme $theme): ?PdfDocumentProvider
    {
        $provider = $this->providers[$document] ?? null;

        return $provider !== null && $provider->supports($theme) ? $provider : null;
    }
}
