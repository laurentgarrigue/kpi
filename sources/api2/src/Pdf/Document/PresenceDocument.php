<?php

namespace App\Pdf\Document;

use App\Pdf\InvalidPdfRequest;
use App\Pdf\PdfAccessDenied;
use App\Pdf\PdfAssets;
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
 * Presence sheets (`presence`, PDF_GOTENBERG.md § 7.2): the nine legacy variants become views and options.
 *
 * - `view=team` (default): one page per team (ex-FeuillePresence, …EN with `lang=en`); `visa=1` adds a signature
 *   column (ex-FeuillePresenceVisa, ROLE_ORGANIZER);
 * - `view=photos`: roster with photos and birth dates (ex-FeuillePresencePhoto, …PhotoRef for the referees pool);
 * - `view=category`: every player of the competition by category (ex-FeuillePresenceCat); `category=U21` keeps the
 *   under-21 players with their birth date (ex-FeuillePresenceU21, ROLE_ORGANIZER);
 * - `view=birthyear`: every player by year and month of birth (ex-FeuillePresenceNaissance, ROLE_ADMIN).
 *
 * A user limited to some clubs only gets the teams of these clubs; asking for a team of another club is refused.
 */
final class PresenceDocument implements PdfDocumentProvider
{
    public const VIEWS = ['team', 'photos', 'category', 'birthyear'];
    public const UNDER_21 = 'U21';

    private const MONTHS = [
        'fr' => ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'],
        'en' => ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
    ];

    public function __construct(
        private readonly PresenceRepository $presence,
        private readonly PdfCompetitions $competitions,
        private readonly PdfAssets $assets,
        private readonly PdfLabels $labels,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::PRESENCE;
    }

    public function supports(PdfTheme $theme): bool
    {
        return $theme === PdfTheme::Admin;
    }

    public function options(): array
    {
        return ['view', 'visa', 'category'];
    }

    public function content(PdfRequest $request, PdfTheme $theme, ?array $allowedClubs): ?PdfContent
    {
        $view = $request->option('view') ?? 'team';
        if (!in_array($view, self::VIEWS, true)) {
            throw new InvalidPdfRequest('Parameter "view" must be one of ' . implode(', ', self::VIEWS) . '.');
        }
        $code = $request->requireCompetition();
        $refereesPool = $code === RefereesPoolDocument::POOL_COMPETITION;
        // The referees pool is permanent, stored under a technical season (FeuillePresencePhotoRef).
        $season = $refereesPool ? RefereesPoolDocument::POOL_SEASON : $request->season;
        $labels = $this->labels->forLanguage($request->lang);
        $header = $this->competitions->header($season, $code)
            ?? ($refereesPool ? ['competition' => ['code' => $code, 'title' => $labels['referees_pool.title'], 'subtitle' => null, 'logo' => null, 'type' => ''], 'images' => []] : null);
        if ($header === null) {
            return null;
        }
        if ($request->team !== null) {
            $team = $this->presence->team($request->team);
            if ($team === null || $team['competition'] !== $code || $team['season'] !== $season) {
                return null;
            }
            if ($allowedClubs !== null && !in_array($team['club'], $allowedClubs, true)) {
                throw new PdfAccessDenied('Team of a club out of the perimeter.');
            }
        }

        $teams = $this->presence->teams($season, $code, $request->team, $allowedClubs);
        $teamNames = array_column($teams, 'name', 'id');
        $players = $this->presence->players(array_keys($teamNames));
        if ($players === []) {
            return null;
        }

        $images = $header['images'];
        [$title, $columns, $sections] = match ($view) {
            'team' => $this->byTeam($players, $teamNames, $request, $labels),
            'photos' => $this->withPhotos($players, $teamNames, $request, $labels, $refereesPool, $images),
            'category' => $this->byCategory($players, $teamNames, $request, $labels),
            'birthyear' => $this->byBirthYear($players, $teamNames, $request, $labels),
        };
        if ($sections === []) {
            return null;
        }

        return new PdfContent(
            template: 'sections.html.twig',
            title: $title,
            filename: sprintf('%s-%s_%s_%s_%s.pdf', $this->document(), $view, $code, $request->season, $request->lang),
            variables: [
                'competition' => $header['competition'],
                'columns' => $columns,
                'sections' => $sections,
                'page_per_section' => $view === 'team' || $view === 'photos',
                'dense' => $view !== 'photos',
                'internal' => true,
            ],
            landscape: true,
            images: $images,
        );
    }

    /**
     * @param list<array<string, mixed>> $players
     * @param array<int, string>         $teamNames
     * @param array<string, string>      $labels
     *
     * @return array{string, list<array<string, string>>, list<array<string, mixed>>}
     */
    private function byTeam(array $players, array $teamNames, PdfRequest $request, array $labels): array
    {
        $columns = [
            ...$this->identityColumns($labels),
            ['key' => 'category', 'label' => $labels['player.category']],
            ['key' => 'paddle', 'label' => $labels['player.paddle']],
            ['key' => 'certificate', 'label' => $labels['player.certificate']],
            ['key' => 'club', 'label' => $labels['player.club']],
            ['key' => 'referee', 'label' => $labels['player.referee']],
        ];
        if ($request->option('visa') === '1') {
            $columns[] = ['key' => 'visa', 'label' => $labels['player.visa'], 'kind' => 'blank', 'class' => 'pdf-col--signature'];
        }

        $sections = [];
        foreach ($this->groupByTeam($players) as $teamId => $teamPlayers) {
            $sections[] = [
                'title' => $teamNames[$teamId] ?? '',
                'rows' => array_map(fn (array $player): array => $this->row($player, $request->season), $teamPlayers),
            ];
        }

        return [$labels['presence.title'], $columns, $sections];
    }

    /**
     * @param list<array<string, mixed>> $players
     * @param array<int, string>         $teamNames
     * @param array<string, string>      $labels
     * @param array<string, string>      $images     filled with the photos found
     *
     * @return array{string, list<array<string, string>>, list<array<string, mixed>>}
     */
    private function withPhotos(array $players, array $teamNames, PdfRequest $request, array $labels, bool $refereesPool, array &$images): array
    {
        $columns = [
            ...($refereesPool ? [] : [
                ['key' => 'number', 'label' => $labels['player.number'], 'class' => 'num'],
                ['key' => 'role', 'label' => $labels['player.role']],
            ]),
            ['key' => 'last_name', 'label' => $labels['player.last_name']],
            ['key' => 'first_name', 'label' => $labels['player.first_name']],
            ['key' => 'birthdate', 'label' => $labels['player.birthdate']],
            ['key' => 'photo', 'label' => $labels['player.photo'], 'kind' => 'image'],
            ['key' => 'licence', 'label' => $labels['player.licence']],
            ['key' => 'check', 'label' => $labels['player.check'], 'kind' => 'box'],
        ];

        $sections = [];
        foreach ($this->groupByTeam($players) as $teamId => $teamPlayers) {
            $rows = [];
            foreach ($teamPlayers as $player) {
                $photo = $this->assets->legacyImage(sprintf('KIP/players/%d.png', (int) $player['Matric']));
                if ($photo !== null) {
                    $images[$photo['name']] = $photo['content'];
                }
                $rows[] = [...$this->row($player, $request->season), 'photo' => $photo['name'] ?? null];
            }
            $sections[] = ['title' => $teamNames[$teamId] ?? '', 'rows' => $rows];
        }

        return [$refereesPool ? $labels['presence.referees'] : $labels['presence.photos_title'], $columns, $sections];
    }

    /**
     * @param list<array<string, mixed>> $players
     * @param array<int, string>         $teamNames
     * @param array<string, string>      $labels
     *
     * @return array{string, list<array<string, string>>, list<array<string, mixed>>}
     */
    private function byCategory(array $players, array $teamNames, PdfRequest $request, array $labels): array
    {
        $filter = $request->option('category');
        $under21 = $filter !== null && strcasecmp($filter, self::UNDER_21) === 0;
        $columns = [
            ...$this->identityColumns($labels),
            ['key' => 'team', 'label' => $labels['player.team']],
            ...($under21 ? [['key' => 'birthdate', 'label' => $labels['player.birthdate']]] : []),
            ['key' => 'paddle', 'label' => $labels['player.paddle']],
            ['key' => 'certificate', 'label' => $labels['player.certificate']],
            ['key' => 'club', 'label' => $labels['player.club']],
            ['key' => 'referee', 'label' => $labels['player.referee']],
        ];

        $groups = [];
        foreach ($players as $player) {
            if ($under21 && !PresencePlayer::isUnder21($player['Naissance'], $request->season)) {
                continue;
            }
            $category = (string) $player['Categ'];
            if ($filter !== null && !$under21 && strcasecmp($category, $filter) !== 0) {
                continue;
            }
            $groups[$under21 ? self::UNDER_21 : $category][] = [
                ...$this->row($player, $request->season),
                'team' => $teamNames[(int) $player['Id_equipe']] ?? '',
            ];
        }
        ksort($groups);

        $sections = [];
        foreach ($groups as $category => $rows) {
            $sections[] = ['title' => (string) $category, 'rows' => $rows];
        }

        return [$under21 ? $labels['presence.u21_title'] : $labels['presence.category_title'], $columns, $sections];
    }

    /**
     * @param list<array<string, mixed>> $players
     * @param array<int, string>         $teamNames
     * @param array<string, string>      $labels
     *
     * @return array{string, list<array<string, string>>, list<array<string, mixed>>}
     */
    private function byBirthYear(array $players, array $teamNames, PdfRequest $request, array $labels): array
    {
        $columns = [
            ...$this->identityColumns($labels),
            ['key' => 'team', 'label' => $labels['player.team']],
            ['key' => 'club', 'label' => $labels['player.club']],
            ['key' => 'month', 'label' => $labels['player.birth_month']],
        ];

        $groups = [];
        foreach ($players as $player) {
            $birthDate = (string) $player['Naissance'];
            $month = (int) substr($birthDate, 5, 2);
            $groups[substr($birthDate, 0, 4) ?: '—'][] = [
                ...$this->row($player, $request->season),
                'team' => $teamNames[(int) $player['Id_equipe']] ?? '',
                'month' => self::MONTHS[$request->lang][$month] ?? '',
                '_sort' => sprintf('%02d %s %s', $month, $player['Nom'], $player['Prenom']),
            ];
        }
        ksort($groups);

        $sections = [];
        foreach ($groups as $year => $rows) {
            usort($rows, static fn (array $a, array $b): int => strcmp($a['_sort'], $b['_sort']));
            $sections[] = ['title' => (string) $year, 'rows' => $rows];
        }

        return [$labels['presence.birthyear_title'], $columns, $sections];
    }

    /**
     * @param array<string, string> $labels
     *
     * @return list<array<string, string>>
     */
    private function identityColumns(array $labels): array
    {
        return [
            ['key' => 'number', 'label' => $labels['player.number'], 'class' => 'num'],
            ['key' => 'role', 'label' => $labels['player.role']],
            ['key' => 'licence', 'label' => $labels['player.licence']],
            ['key' => 'last_name', 'label' => $labels['player.last_name']],
            ['key' => 'first_name', 'label' => $labels['player.first_name']],
        ];
    }

    /**
     * @param array<string, mixed> $player
     *
     * @return array<string, string|null>
     */
    private function row(array $player, string $season): array
    {
        return [
            'number' => $player['Numero'] === null ? '' : (string) $player['Numero'],
            'role' => PresencePlayer::role($player['Capitaine']),
            'licence' => PresencePlayer::licence((int) $player['Matric'], $player['Reserve'], $player['Origine'], $season),
            'last_name' => PresencePlayer::lastName($player['Nom']),
            'first_name' => PresencePlayer::firstName($player['Prenom']),
            'category' => (string) $player['Categ'],
            'paddle' => PresencePlayer::paddle($player['Pagaie_ECA'], $player['Pagaie_EVI'], $player['Pagaie_MER']),
            'certificate' => (string) $player['Etat_certificat_CK'],
            'club' => (string) $player['Numero_club'],
            'referee' => PresencePlayer::referee($player['arbitre'], $player['niveau']),
            'birthdate' => self::date($player['Naissance']),
            '_class' => in_array($player['Capitaine'], ['E', 'A', 'X'], true) ? 'pdf-row--muted' : '',
        ];
    }

    /**
     * @param list<array<string, mixed>> $players
     *
     * @return array<int, list<array<string, mixed>>>
     */
    private function groupByTeam(array $players): array
    {
        $teams = [];
        foreach ($players as $player) {
            $teams[(int) $player['Id_equipe']][] = $player;
        }

        return $teams;
    }

    private static function date(?string $date): ?string
    {
        if ($date === null || $date === '' || str_starts_with($date, '0000')) {
            return null;
        }
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', substr($date, 0, 10));

        return $parsed === false ? $date : $parsed->format('d/m/Y');
    }
}
