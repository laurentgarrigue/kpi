<?php

namespace App\Pdf;

use App\PublicResults\CompetitionRules;
use Doctrine\DBAL\Connection;

/**
 * Header of the admin documents of a competition, published or not (§ 8.3): display title, subtitle and logo of the
 * competition, the logo being embedded from the legacy media tree.
 */
class PdfCompetitions
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PdfAssets $assets,
    ) {
    }

    /** @return array{competition: array{code: string, title: string, subtitle: ?string, logo: ?string, type: string}, images: array<string, string>}|null */
    public function header(string $season, string $code): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT Code, Libelle, Soustitre, Soustitre2, Titre_actif, Logo_actif, LogoLink, Code_typeclt
             FROM kp_competition WHERE Code = ? AND Code_saison = ?',
            [$code, $season],
        );
        if ($row === false) {
            return null;
        }
        $logo = $this->assets->legacyImage(CompetitionRules::visual((string) $row['Logo_actif'], $row['LogoLink']));

        return [
            'competition' => [
                'code' => $code,
                'title' => CompetitionRules::displayTitle((string) $row['Titre_actif'], $row['Libelle'], $row['Soustitre']),
                'subtitle' => ($row['Soustitre2'] ?? '') !== '' ? $row['Soustitre2'] : null,
                'logo' => $logo['name'] ?? null,
                'type' => (string) $row['Code_typeclt'],
            ],
            'images' => $logo === null ? [] : [$logo['name'] => $logo['content']],
        ];
    }
}
