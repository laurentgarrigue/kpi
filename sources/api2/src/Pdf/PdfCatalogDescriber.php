<?php

namespace App\Pdf;

use App\RuleCatalog\RuleCatalogDescriber;
use App\RuleCatalog\RuleCatalogDescription;
use App\RuleCatalog\RuleSection;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;

/** Values of PdfCatalog for the `rules-catalog` PDF (FEATURE_RULES_CATALOG_PDF.md § 4.1). */
#[AsTaggedItem(priority: 10)]
final class PdfCatalogDescriber implements RuleCatalogDescriber
{
    public const CODE = 'pdf-documents';

    public function code(): string
    {
        return self::CODE;
    }

    public function describe(string $season): RuleCatalogDescription
    {
        return new RuleCatalogDescription(
            title: 'Documents PDF',
            version: null,
            sources: ['sources/api2/src/Pdf/PdfCatalog.php', 'DOC/specs/PDF_GOTENBERG.md'],
            sections: [
                RuleSection::table(
                    'Documents et droits',
                    ['document' => 'Document', 'role' => 'Rôle minimal (admin)', 'options' => 'Options soumises à un rôle', 'public' => 'Version publique'],
                    array_map(static fn (string $document): array => [
                        'document' => $document,
                        'role' => PdfCatalog::adminRole($document) ?? '—',
                        'options' => RuleSection::list(array_map(
                            static fn (string $option, string $role): string => sprintf('%s : %s', $option, $role),
                            array_keys(PdfCatalog::optionRolesOf($document)),
                            PdfCatalog::optionRolesOf($document),
                        )),
                        'public' => RuleSection::yesNo(PdfCatalog::isPublicDocument($document)),
                    ], PdfCatalog::documents()),
                ),
                RuleSection::lines('Rôles et profils', [
                    'ROLE_SUPER_ADMIN = profil 1 · ROLE_ADMIN = 1–2 · ROLE_ORGANIZER = 1–6 · ROLE_VIEWER = 1–8 · ROLE_SCORER = 1–9.',
                    'ROLE_PRESENCE_VIEWER = 1–8 et 10 · ROLE_SCRUTINEER = 1, 2 et 10 (PDF_GOTENBERG.md § 7).',
                ]),
            ],
        );
    }
}
