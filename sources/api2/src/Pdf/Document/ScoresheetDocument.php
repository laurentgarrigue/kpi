<?php

namespace App\Pdf\Document;

use App\Pdf\Games\GamesScope;
use App\Pdf\Games\GamesSort;
use App\Pdf\InvalidPdfRequest;
use App\Pdf\PdfAccessDenied;
use App\Pdf\PdfAssets;
use App\Pdf\PdfCatalog;
use App\Pdf\PdfCompetitions;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfHeader;
use App\Pdf\PdfLabels;
use App\Pdf\PdfPlace;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use App\Pdf\Scoresheet\ScoresheetRepository;
use App\Pdf\Scoresheet\ScoresheetSheet;
use App\PublicResults\MatchLabelParser;

/**
 * Scoresheets (`scoresheet`, ex-admin/FeuilleMatchMulti.php, PDF_GOTENBERG.md § 7.3.3), admin only (ROLE_SCORER):
 * one A4 landscape page per game, the games given by `games` or by the scope of the Games page, with the visibility
 * of that page (profile 7: published games). Official document: fields and rubrics of the legacy sheet.
 */
final class ScoresheetDocument implements PdfDocumentProvider
{
    /** An elimination game must have a winner. */
    private const ELIMINATION = 'E';

    private const HALF_TIME_PASSED_STATUS = 'END';
    private const PLAYING_STATUS = 'ON';
    private const FIRST_PERIOD = 'M1';
    private const INTERNATIONAL = 'INT';

    public function __construct(
        private readonly GamesScope $scope,
        private readonly ScoresheetRepository $sheets,
        private readonly MatchLabelParser $labelParser,
        private readonly PdfCompetitions $competitions,
        private readonly PdfAssets $assets,
        private readonly PdfLabels $labels,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::SCORESHEET;
    }

    public function supports(PdfTheme $theme): bool
    {
        return $theme === PdfTheme::Admin;
    }

    public function options(): array
    {
        return GamesScope::FILTER_OPTIONS;
    }

    public function content(PdfRequest $request, PdfTheme $theme, ?array $allowedClubs): ?PdfContent
    {
        $selected = $this->scope->games($request, $theme, $request->games === [] ? null : GamesSort::Date);
        if ($request->games !== [] && count($selected) < count($request->games)) {
            // A game out of the perimeter (or unknown): refused without telling which (§ 6.4).
            throw new PdfAccessDenied('Game out of the perimeter.');
        }
        if (count($selected) > PdfRequest::MAX_GAMES) {
            throw new InvalidPdfRequest(sprintf('At most %d games per document.', PdfRequest::MAX_GAMES));
        }
        $ids = $request->games !== [] ? $request->games : array_map('intval', array_column($selected, 'Id'));
        $games = $this->sheets->games($ids);
        if ($games === []) {
            return null;
        }

        $images = [];
        $sheets = [];
        foreach ($games as $game) {
            $sheets[] = $this->sheet($game, $request->lang, $images);
        }

        return new PdfContent(
            template: 'scoresheet.html.twig',
            title: $this->labels->forLanguage($request->lang)['scoresheet.title'],
            filename: sprintf('%s_%s_%s.pdf', $this->document(), count($ids) === 1 ? $ids[0] : count($ids) . '-games', $request->season),
            variables: [
                'sheets' => $sheets,
                'internal' => true,
            ],
            landscape: true,
            images: $images,
        );
    }

    /**
     * @param array<string, mixed>  $game
     * @param array<string, string> $images images of the document, completed with the visuals of the competition
     *
     * @return array<string, mixed>
     */
    private function sheet(array $game, string $lang, array &$images): array
    {
        $labels = $this->labels->forLanguage($lang);
        // Visuals of the competition of the game, inside the sheet: one PDF may hold several competitions (§ 8.4.1).
        $visuals = $this->competitions->header((string) $game['Code_saison'], (string) $game['Code_competition']);
        $images += $visuals['images'] ?? [];
        $heading = PdfHeader::scoresheetVisuals($visuals['competition'] ?? null);
        foreach (array_intersect($heading['logos'], PdfAssets::SCORESHEET_LOGOS) as $logo) {
            $images[$logo] ??= $this->assets->charterLogo($logo)['content'];
        }
        $international = $game['Code_niveau'] === self::INTERNATIONAL;
        $season = (string) $game['Code_saison'];
        $placeholders = $this->labelParser->parse((string) $game['Libelle']);
        $teamA = $game['LibelleA'] ?? ($placeholders[0] ?? '');
        $teamB = $game['LibelleB'] ?? ($placeholders[1] ?? '');
        $events = ScoresheetSheet::events($this->sheets->events((int) $game['Id']), $labels);
        $scoreA = ScoresheetSheet::score($game['ScoreA']);
        $scoreB = ScoresheetSheet::score($game['ScoreB']);
        $status = (string) $game['Statut'];
        $period = (string) $game['Periode'];
        $titleActive = (string) $game['Titre_actif'];

        return [
            'labels' => $labels,
            'banner' => $heading['banner'],
            'logos' => $heading['logos'],
            'sponsor' => $visuals['competition']['sponsor'] ?? null,
            'id' => (int) $game['Id'],
            'competition' => $titleActive === 'O' ? (string) $game['Competition'] : (string) $game['Soustitre'],
            'category' => (string) $game['Soustitre2'],
            'season' => $season,
            'organiser' => (string) $game['Organisateur'],
            'manager' => ScoresheetSheet::person($game['Responsable_R1'] ?: $game['Responsable_insc']),
            'delegate' => ScoresheetSheet::person($game['Delegue'] ?: $game['ChefArbitre']),
            'place' => PdfPlace::label($game['Lieu'], $game['Departement']),
            'date' => self::date((string) $game['Date_match'], $lang),
            'time' => substr((string) $game['Heure_match'], 0, 5),
            'end_time' => in_array(substr((string) $game['Heure_fin'], 0, 5), ['', '00:00'], true) ? '' : substr((string) $game['Heure_fin'], 0, 5),
            'pitch' => (string) $game['Terrain'],
            'phase' => (string) $game['Phase'],
            'number' => (string) $game['Numero_ordre'],
            'label' => str_replace(['[', ']'], '', (string) $game['Libelle']),
            'referee_1' => ScoresheetSheet::referee($game['Arbitre_principal'] ?: ($placeholders[2] ?? null)),
            'referee_2' => ScoresheetSheet::referee($game['Arbitre_secondaire'] ?: ($placeholders[3] ?? null)),
            'secretary' => (string) $game['Secretaire'],
            'timekeeper' => (string) $game['Chronometre'],
            'shot_clock' => (string) $game['Timeshoot'],
            'line_judges' => trim(implode(' / ', array_filter([(string) $game['Ligne1'], (string) $game['Ligne2']], 'strlen'))),
            'elimination' => $game['Type'] === self::ELIMINATION,
            'international' => $international,
            'team_a' => ['name' => $teamA, 'colour' => (string) $game['ColorA'], 'roster' => ScoresheetSheet::roster($this->sheets->players((int) $game['Id'], self::team($game['Id_equipeA']), 'A'), $season, $international, $labels)],
            'team_b' => ['name' => $teamB, 'colour' => (string) $game['ColorB'], 'roster' => ScoresheetSheet::roster($this->sheets->players((int) $game['Id'], self::team($game['Id_equipeB']), 'B'), $season, $international, $labels)],
            'events' => $events['lines'],
            'half_time' => $status === self::HALF_TIME_PASSED_STATUS || ($status === self::PLAYING_STATUS && $period !== self::FIRST_PERIOD && $period !== '')
                ? $events['half_time']
                : null,
            'score_a' => $scoreA,
            'score_b' => $scoreB,
            'provisional' => ScoresheetSheet::isProvisional($events['goals'], $scoreA, $scoreB),
            'comments' => (string) $game['Commentaires_officiels'],
        ];
    }

    private static function team(mixed $id): ?int
    {
        return $id === null || (int) $id < 1 ? null : (int) $id;
    }

    private static function date(string $date, string $lang): string
    {
        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $parsed === false ? '' : $parsed->format($lang === 'en' ? 'Y-m-d' : 'd/m/Y');
    }
}
