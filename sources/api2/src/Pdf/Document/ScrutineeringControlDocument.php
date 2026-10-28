<?php

namespace App\Pdf\Document;

use App\Pdf\PdfAccessDenied;
use App\Pdf\PdfCatalog;
use App\Pdf\PdfCompetitions;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use App\Pdf\Presence\PresencePlayer;
use App\Pdf\Presence\PresenceRepository;

/**
 * Equipment control sheet (`scrutineering-control`, ex-FeuilleControle / …EN, PDF_GOTENBERG.md § 7.2): one page per
 * team with the status of the kayak, vest and helmet and the number of paddles recorded by the scrutineering page;
 * the staff follows the players. ROLE_SCRUTINEER (profiles 1, 2, 10), every team of the competitions of the mandate.
 */
final class ScrutineeringControlDocument implements PdfDocumentProvider
{
    /** Roles listed under « Staff »: E (staff), A (referee), X (inactive). */
    private const STAFF_ROLES = ['E', 'A', 'X'];

    public function __construct(
        private readonly PresenceRepository $presence,
        private readonly PdfCompetitions $competitions,
        private readonly PdfLabels $labels,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::SCRUTINEERING_CONTROL;
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
        if ($request->team !== null) {
            $team = $this->presence->team($request->team);
            if ($team === null || $team['competition'] !== $code || $team['season'] !== $request->season) {
                return null;
            }
            if ($allowedClubs !== null && !in_array($team['club'], $allowedClubs, true)) {
                throw new PdfAccessDenied('Team of a club out of the perimeter.');
            }
        }

        $teams = array_column($this->presence->teams($request->season, $code, $request->team, $allowedClubs), 'name', 'id');
        $labels = $this->labels->forLanguage($request->lang);
        $sections = [];
        $byTeam = [];
        foreach ($this->presence->players(array_keys($teams)) as $player) {
            $byTeam[(int) $player['Id_equipe']][] = $player;
        }
        foreach ($teams as $teamId => $name) {
            if (isset($byTeam[$teamId])) {
                $sections[] = ['title' => $name, 'rows' => self::rows($byTeam[$teamId], $request->season, $labels)];
            }
        }
        if ($sections === []) {
            return null;
        }

        return new PdfContent(
            template: 'sections.html.twig',
            title: $labels['control.title'],
            filename: sprintf('%s_%s_%s_%s.pdf', $this->document(), $code, $request->season, $request->lang),
            variables: [
                'competition' => $header['competition'],
                'columns' => [
                    ['key' => 'number', 'label' => $labels['player.number'], 'class' => 'num'],
                    ['key' => 'role', 'label' => $labels['player.role']],
                    ['key' => 'licence', 'label' => $labels['player.licence']],
                    ['key' => 'last_name', 'label' => $labels['player.last_name']],
                    ['key' => 'first_name', 'label' => $labels['player.first_name']],
                    ['key' => 'kayak', 'label' => $labels['control.kayak']],
                    ['key' => 'vest', 'label' => $labels['control.vest']],
                    ['key' => 'helmet', 'label' => $labels['control.helmet']],
                    ['key' => 'paddles', 'label' => $labels['control.paddles'], 'class' => 'num'],
                ],
                'sections' => $sections,
                'page_per_section' => true,
                'internal' => true,
            ],
            landscape: true,
            images: $header['images'],
        );
    }

    /**
     * Rows of one team, players first, then a « Staff » separator before the first staff member.
     *
     * @param list<array<string, mixed>> $players in the order of the sheets (PresenceRepository::players())
     * @param array<string, string>      $labels
     *
     * @return list<array<string, mixed>>
     */
    public static function rows(array $players, string $season, array $labels): array
    {
        $rows = [];
        $staff = false;
        foreach ($players as $player) {
            if (!$staff && in_array($player['Capitaine'], self::STAFF_ROLES, true)) {
                $staff = true;
                $rows[] = ['_group' => $labels['player.staff']];
            }
            $rows[] = [
                'number' => $player['Numero'] === null ? '' : (string) $player['Numero'],
                'role' => PresencePlayer::role($player['Capitaine']),
                'licence' => PresencePlayer::licence((int) $player['Matric'], $player['Reserve'], $player['Origine'], $season),
                'last_name' => PresencePlayer::lastName($player['Nom']),
                'first_name' => PresencePlayer::firstName($player['Prenom']),
                'kayak' => self::status($player['kayak_status'], $labels),
                'vest' => self::status($player['vest_status'], $labels),
                'helmet' => self::status($player['helmet_status'], $labels),
                'paddles' => $player['paddle_count'] === null ? '' : (string) $player['paddle_count'],
            ];
        }

        return $rows;
    }

    /** @param array<string, string> $labels */
    private static function status(mixed $status, array $labels): string
    {
        $key = PresencePlayer::CONTROL_STATUSES[(int) $status] ?? null;

        return $key === null ? '' : $labels['control.status_' . $key];
    }
}
