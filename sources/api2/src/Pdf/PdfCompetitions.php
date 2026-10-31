<?php

namespace App\Pdf;

use App\PublicResults\CompetitionRules;
use Doctrine\DBAL\Connection;

/**
 * Header of the documents of a competition (§ 8.3): display title, subtitle and logo of the competition, the logo
 * being embedded from the legacy media tree, plus the rules the documents need (status, final round, marks).
 */
class PdfCompetitions
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PdfAssets $assets,
    ) {
    }

    /**
     * Header of a competition, published or not (admin documents).
     *
     * @return array{
     *     competition: array{code: string, title: string, subtitle: ?string, logo: ?string, type: string},
     *     images: array<string, string>,
     *     rules: array{status: string, final: bool, qualified: int, eliminated: int, published: bool, forfeits: bool}
     * }|null
     */
    public function header(string $season, string $code): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT Code, Libelle, Soustitre, Soustitre2, Titre_actif, Logo_actif, LogoLink, Code_typeclt, Statut,
                    Code_tour, Qualifies, Elimines, Publication, Points
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
                'type' => (string) ($row['Code_typeclt'] ?: 'CHPT'),
            ],
            'images' => $logo === null ? [] : [$logo['name'] => $logo['content']],
            'rules' => [
                'status' => (string) $row['Statut'],
                'final' => CompetitionRules::isFinalRound($row['Code_tour'] === null ? null : (int) $row['Code_tour']),
                'qualified' => (int) $row['Qualifies'],
                'eliminated' => (int) $row['Elimines'],
                'published' => $row['Publication'] === 'O',
                // Forfeits only count, and are only shown, with the 4-2-1-0 scale (PdfCltNiveauPhase.php).
                'forfeits' => ($row['Points'] ?? '') === '4-2-1-0',
            ],
        ];
    }

    /** Header of a published competition (public documents), or null. */
    public function publishedHeader(string $season, string $code): ?array
    {
        $header = $this->header($season, $code);

        return $header !== null && $header['rules']['published'] ? $header : null;
    }
}
