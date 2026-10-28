<?php

namespace App\Pdf;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/** Builds the content of one document of the catalogue (PdfCatalog), for the admin or the public audience. */
#[AutoconfigureTag(self::TAG)]
interface PdfDocumentProvider
{
    public const TAG = 'app.pdf_document_provider';

    /** Document id of the catalogue, e.g. `teams-groups`. */
    public function document(): string;

    public function supports(PdfTheme $theme): bool;

    /** @return list<string> document options accepted in the query (`view`, `visa`…) */
    public function options(): array;

    /**
     * Content of the document, or null when there is nothing to print (404).
     *
     * @param string[]|null $allowedClubs clubs the user may see (admin, limited profiles), null when unrestricted
     *
     * @throws InvalidPdfRequest
     */
    public function content(PdfRequest $request, PdfTheme $theme, ?array $allowedClubs): ?PdfContent;
}
