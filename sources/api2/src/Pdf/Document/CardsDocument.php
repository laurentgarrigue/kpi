<?php

namespace App\Pdf\Document;

use App\Pdf\PdfCatalog;
use App\Pdf\PdfCompetitions;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use App\Pdf\Presence\PresencePlayer;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Card follow-up of a competition (`cards`, ex-FeuilleCards, PDF_GOTENBERG.md § 7.5), admin only (ROLE_ORGANIZER):
 * per team, the cards of each player over the season, then empty columns to tally those of the gameday on site.
 */
final class CardsDocument implements PdfDocumentProvider
{
    private const CARDS = ['green' => 'V', 'yellow' => 'J', 'red' => 'R', 'definitive' => 'D'];

    public function __construct(
        private readonly Connection $connection,
        private readonly PdfCompetitions $competitions,
        private readonly PdfLabels $labels,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::CARDS;
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
        $labels = $this->labels->forLanguage($request->lang);

        $sections = [];
        foreach ($this->players($request->season, $code, $allowedClubs) as $player) {
            $sections[$player['Libelle']] ??= ['title' => (string) $player['Libelle'], 'rows' => []];
            $row = [
                'role' => PresencePlayer::role($player['Capitaine']),
                'player' => trim(sprintf('%s - %s %s', $player['Numero'], PresencePlayer::lastName($player['Nom']), PresencePlayer::firstName($player['Prenom'])), ' -'),
            ];
            foreach (array_keys(self::CARDS) as $card) {
                $row['season_' . $card] = (int) $player[$card] === 0 ? '' : (string) $player[$card];
            }
            $sections[$player['Libelle']]['rows'][] = $row;
        }
        if ($sections === []) {
            return null;
        }

        $cardColumns = static fn (string $prefix, string $class): array => array_map(static fn (string $card): array => [
            'key' => $prefix . $card,
            'label' => $labels['cards.' . $card],
            'class' => $class . ' pdf-card--' . $card,
        ], array_keys(self::CARDS));

        return new PdfContent(
            template: 'sections.html.twig',
            title: $labels['cards.title'],
            filename: sprintf('%s_%s_%s_%s.pdf', $this->document(), $code, $request->season, $request->lang),
            variables: [
                'competition' => $header['competition'],
                'columns' => [
                    ['key' => 'role', 'label' => $labels['player.role']],
                    ['key' => 'player', 'label' => $labels['cards.player']],
                    ...$cardColumns('season_', 'num'),
                    ['key' => 'notes', 'label' => $labels['cards.notes'], 'class' => 'pdf-col--notes'],
                    ...$cardColumns('gameday_', 'pdf-col--tally'),
                ],
                'sections' => array_values($sections),
                'note' => $labels['cards.legend'],
                'dense' => true,
                'internal' => true,
            ],
            images: $header['images'],
        );
    }

    /**
     * Players of the teams having games in the competition, with their cards of the season (legacy query).
     *
     * @param string[]|null $allowedClubs
     *
     * @return list<array<string, mixed>>
     */
    private function players(string $season, string $code, ?array $allowedClubs): array
    {
        $sums = implode(', ', array_map(
            static fn (string $card, string $event): string => sprintf("SUM(md.Id_evt_match = '%s') AS %s", $event, $card),
            array_keys(self::CARDS),
            self::CARDS,
        ));
        $clubFilter = $allowedClubs === null ? '' : ' AND ce.Code_club IN (?)';

        return $this->connection->fetchAllAssociative(
            "SELECT ce.Id, ce.Libelle, j.Matric, j.Numero, j.Nom, j.Prenom, j.Capitaine, $sums
             FROM kp_competition_equipe_joueur j
             JOIN kp_competition_equipe ce ON ce.Id = j.Id_equipe
             JOIN kp_match m ON ce.Id IN (m.Id_equipeA, m.Id_equipeB)
             JOIN kp_journee jo ON jo.Id = m.Id_journee
             LEFT JOIN kp_match_detail md ON md.Id_match = m.Id AND md.Competiteur = j.Matric
             WHERE jo.Code_saison = ? AND jo.Code_competition = ?$clubFilter
             GROUP BY ce.Id, ce.Libelle, j.Matric, j.Numero, j.Nom, j.Prenom, j.Capitaine
             ORDER BY ce.Libelle, FIELD(j.Capitaine, 'E', 'A', 'X'), j.Numero, j.Nom, j.Prenom",
            $allowedClubs === null ? [$season, $code] : [$season, $code, $allowedClubs],
            $allowedClubs === null ? [] : [2 => ArrayParameterType::STRING],
        );
    }
}
