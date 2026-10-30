<?php

namespace App\Pdf;

use App\PublicResults\CompetitionRules;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Header of the documents of a competition (§ 8.3, § 8.4.1): display title, subtitle and visuals of the competition
 * (banner, logo, sponsor, embedded from the legacy media tree), plus the rules the documents need (status, final
 * round, marks).
 */
class PdfCompetitions
{
    /** Visuals of a competition: tick column and file column of kp_competition. */
    private const VISUAL_SOURCES = [
        'banner' => ['Bandeau_actif', 'BandeauLink'],
        'logo' => ['Logo_actif', 'LogoLink'],
        'sponsor' => ['Sponsor_actif', 'SponsorLink'],
    ];
    private const VISUAL_COLUMNS = 'Bandeau_actif, BandeauLink, Logo_actif, LogoLink, Sponsor_actif, SponsorLink, Kpi_ffck_actif';

    /** Keys of the visuals in the competition of a header. */
    public const VISUALS = ['banner', 'logo', 'sponsor', 'charter'];

    public function __construct(
        private readonly Connection $connection,
        private readonly PdfAssets $assets,
    ) {
    }

    /**
     * Header of a competition, published or not (admin documents).
     *
     * @return array{
     *     competition: array{code: string, title: string, subtitle: ?string, type: string, banner: ?string, logo: ?string, sponsor: ?string, charter: bool},
     *     images: array<string, string>,
     *     rules: array{status: string, final: bool, qualified: int, eliminated: int, published: bool, forfeits: bool, section: ?int}
     * }|null
     */
    public function header(string $season, string $code): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT c.Code, c.Libelle, c.Soustitre, c.Soustitre2, c.Titre_actif, c.Code_typeclt, c.Statut, c.Code_tour,
                    c.Qualifies, c.Elimines, c.Publication, c.Points, g.section, ' . self::VISUAL_COLUMNS . '
             FROM kp_competition c
             LEFT JOIN kp_groupe g ON g.Groupe = c.Code_ref
             WHERE c.Code = ? AND c.Code_saison = ?',
            [$code, $season],
        );
        if ($row === false) {
            return null;
        }
        [$visuals, $images] = $this->visuals($row);

        return [
            'competition' => [
                'code' => $code,
                'title' => CompetitionRules::displayTitle((string) $row['Titre_actif'], $row['Libelle'], $row['Soustitre']),
                'subtitle' => ($row['Soustitre2'] ?? '') !== '' ? $row['Soustitre2'] : null,
                'type' => (string) ($row['Code_typeclt'] ?: 'CHPT'),
                ...$visuals,
            ],
            'images' => $images,
            'rules' => [
                'status' => (string) $row['Statut'],
                'final' => CompetitionRules::isFinalRound($row['Code_tour'] === null ? null : (int) $row['Code_tour']),
                'qualified' => (int) $row['Qualifies'],
                'eliminated' => (int) $row['Elimines'],
                'published' => $row['Publication'] === 'O',
                // Forfeits only count, and are only shown, with the 4-2-1-0 scale (PdfCltNiveauPhase.php).
                'forfeits' => ($row['Points'] ?? '') === '4-2-1-0',
                // Section of the group of competitions (kp_groupe.section): 2 national, 3 regional…
                'section' => $row['section'] === null ? null : (int) $row['section'],
            ],
        ];
    }

    /**
     * A header (event, list of competitions…) with the visuals of one competition: the first one of the event or of
     * the list (§ 8.4.1). Unchanged when that competition is unknown, or unpublished for a public document.
     *
     * @param array<string, mixed> $competition
     *
     * @return array{competition: array<string, mixed>, images: array<string, string>}
     */
    public function withVisualsOf(array $competition, ?string $season, ?string $code, bool $publishedOnly): array
    {
        $header = $season === null || $code === null ? null
            : ($publishedOnly ? $this->publishedHeader($season, $code) : $this->header($season, $code));
        if ($header === null) {
            return ['competition' => $competition, 'images' => []];
        }
        $visuals = array_intersect_key($header['competition'], array_flip(self::VISUALS));

        return ['competition' => [...$competition, ...$visuals], 'images' => $header['images']];
    }

    /**
     * First competition of an event, in the order of the event page (§ 8.4.1), published only for a public document.
     *
     * @return array{season: string, code: string}|null
     */
    public function firstOfEvent(int $event, bool $publishedOnly): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT c.Code, c.Code_saison FROM kp_competition c
             WHERE EXISTS (SELECT 1 FROM kp_journee j JOIN kp_evenement_journee ej ON ej.Id_journee = j.Id
                           WHERE ej.Id_evenement = ? AND j.Code_competition = c.Code AND j.Code_saison = c.Code_saison'
                . ($publishedOnly ? " AND j.Publication = 'O'" : '') . ')'
                . ($publishedOnly ? " AND c.Publication = 'O'" : '') . '
             ORDER BY c.GroupOrder, c.Code_tour, c.Code',
            [$event],
        );

        return $row === false ? null : ['season' => (string) $row['Code_saison'], 'code' => (string) $row['Code']];
    }

    /**
     * Visuals of a competition row: each one when ticked and uploaded (an external URL is never fetched, § 9).
     *
     * @param array<string, mixed> $row
     *
     * @return array{array{banner: ?string, logo: ?string, sponsor: ?string, charter: bool}, array<string, string>}
     */
    private function visuals(array $row): array
    {
        $visuals = ['charter' => ($row['Kpi_ffck_actif'] ?? 'O') === 'O'];
        $images = [];
        foreach (self::VISUAL_SOURCES as $visual => [$active, $link]) {
            $image = $this->assets->legacyImage(CompetitionRules::visual((string) $row[$active], $row[$link]));
            $visuals[$visual] = $image['name'] ?? null;
            if ($image !== null) {
                $images[$image['name']] = $image['content'];
            }
        }

        return [$visuals, $images];
    }

    /**
     * Labels of competitions, never their codes (the labels suffice): short label (Soustitre2), else the title.
     *
     * @param list<string> $codes
     *
     * @return array<string, string> label by code, in the order of the codes; an unknown code keeps its code
     */
    public function labels(string $season, array $codes): array
    {
        if ($codes === []) {
            return [];
        }
        $labels = $this->connection->fetchAllKeyValue(
            "SELECT Code, COALESCE(NULLIF(Soustitre2, ''), Libelle) FROM kp_competition WHERE Code_saison = ? AND Code IN (?)",
            [$season, $codes],
            [ParameterType::STRING, ArrayParameterType::STRING],
        );

        return array_combine($codes, array_map(static fn (string $code): string => (string) ($labels[$code] ?? $code), $codes));
    }

    /** Header of a published competition (public documents), or null. */
    public function publishedHeader(string $season, string $code): ?array
    {
        $header = $this->header($season, $code);

        return $header !== null && $header['rules']['published'] ? $header : null;
    }
}
