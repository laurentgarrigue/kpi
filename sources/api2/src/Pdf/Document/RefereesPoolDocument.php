<?php

namespace App\Pdf\Document;

use App\Pdf\PdfCatalog;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use App\Pdf\Presence\PresencePlayer;
use Doctrine\DBAL\Connection;

/**
 * Referees pool (`referees-pool`, ex-FeuillePoolArbitres, PDF_GOTENBERG.md § 7.2): the active referees (status A) of
 * the permanent pool, by group (nation) then by name. The pool does not depend on the season: it is stored under the
 * competition POOL of the technical season 1000; `season` is only the context of the request. ROLE_ADMIN.
 */
final class RefereesPoolDocument implements PdfDocumentProvider
{
    public const POOL_COMPETITION = 'POOL';
    public const POOL_SEASON = '1000';

    public function __construct(
        private readonly Connection $connection,
        private readonly PdfLabels $labels,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::REFEREES_POOL;
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
        $referees = $this->connection->fetchAllAssociative(
            "SELECT e.Libelle, j.Nom, j.Prenom, j.Sexe, a.arbitre, a.niveau
             FROM kp_competition_equipe e
             JOIN kp_competition_equipe_joueur j ON j.Id_equipe = e.Id AND j.Capitaine = 'A'
             LEFT JOIN kp_arbitre a ON a.Matric = j.Matric
             WHERE e.Code_compet = ? AND e.Code_saison = ?
             ORDER BY e.Libelle, j.Nom, j.Prenom",
            [self::POOL_COMPETITION, self::POOL_SEASON],
        );
        if ($referees === []) {
            return null;
        }

        $labels = $this->labels->forLanguage($request->lang);
        $groups = [];
        foreach ($referees as $referee) {
            $groups[(string) $referee['Libelle']][] = [
                'last_name' => PresencePlayer::lastName($referee['Nom']),
                'first_name' => PresencePlayer::firstName($referee['Prenom']),
                'gender' => (string) $referee['Sexe'],
                'qualification' => mb_strtoupper(PresencePlayer::referee($referee['arbitre'], $referee['niveau'])),
            ];
        }
        $sections = [];
        foreach ($groups as $group => $rows) {
            $sections[] = ['title' => $group, 'rows' => $rows];
        }

        return new PdfContent(
            template: 'sections.html.twig',
            title: $labels['referees_pool.title'],
            filename: sprintf('%s_%s.pdf', $this->document(), $request->lang),
            variables: [
                'columns' => [
                    ['key' => 'last_name', 'label' => $labels['player.last_name']],
                    ['key' => 'first_name', 'label' => $labels['player.first_name']],
                    ['key' => 'gender', 'label' => $labels['referees_pool.gender']],
                    ['key' => 'qualification', 'label' => $labels['referees_pool.qualification']],
                ],
                'sections' => $sections,
                'dense' => true,
                'internal' => true,
            ],
        );
    }
}
