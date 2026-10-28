<?php

namespace App\Pdf\Document;

use App\Pdf\PdfCatalog;
use App\Pdf\PdfCompetitions;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use Doctrine\DBAL\Connection;

/**
 * Teams of a competition by pool (`teams-groups`, ex-admin/FeuilleGroups.php, PDF_GOTENBERG.md § 7.2): admin only,
 * reference mock-up of the admin charter (§ 8.6).
 */
final class TeamsGroupsDocument implements PdfDocumentProvider
{
    public function __construct(
        private readonly Connection $connection,
        private readonly PdfCompetitions $competitions,
        private readonly PdfLabels $labels,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::TEAMS_GROUPS;
    }

    public function supports(PdfTheme $theme): bool
    {
        return $theme === PdfTheme::Admin;
    }

    public function options(): array
    {
        return [];
    }

    public function content(PdfRequest $request, PdfTheme $theme, ?array $allowedClubs): ?PdfContent
    {
        $code = $request->requireCompetition();
        $header = $this->competitions->header($request->season, $code);
        if ($header === null) {
            return null;
        }

        $teams = $this->connection->fetchAllAssociative(
            'SELECT ce.Id, ce.Libelle, ce.Code_club, cl.Libelle AS club, ce.Poule, ce.Tirage
             FROM kp_competition_equipe ce
             LEFT JOIN kp_club cl ON cl.Code = ce.Code_club
             WHERE ce.Code_compet = ? AND ce.Code_saison = ?
             ORDER BY ce.Poule, ce.Tirage, ce.Libelle',
            [$code, $request->season],
        );
        if ($teams === []) {
            return null;
        }

        $pools = [];
        foreach ($teams as $team) {
            $pools[trim((string) $team['Poule'])][] = [
                'name' => $team['Libelle'],
                'club' => $team['club'] ?? $team['Code_club'],
                'draw' => (int) $team['Tirage'] > 0 ? (int) $team['Tirage'] : null,
            ];
        }

        $title = $this->labels->forLanguage($request->lang)['teams_groups.title'];

        return new PdfContent(
            template: 'teams_groups.html.twig',
            title: $title,
            filename: sprintf('%s_%s_%s_%s.pdf', $this->document(), $code, $request->season, $request->lang),
            variables: [
                'competition' => $header['competition'],
                'pools' => $pools,
                'team_count' => count($teams),
                'internal' => false,
            ],
            images: $header['images'],
        );
    }
}
