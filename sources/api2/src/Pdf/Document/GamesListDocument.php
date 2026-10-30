<?php

namespace App\Pdf\Document;

use App\Pdf\Games\GamesScope;
use App\Pdf\Games\GamesSort;
use App\Pdf\PdfCatalog;
use App\Pdf\PdfContent;
use App\Pdf\PdfDocumentProvider;
use App\Pdf\PdfLabels;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;
use App\Pdf\Ranking\RankingTable;
use App\PublicResults\CompetitionRules;

/**
 * Game list (`games-list`, ex-PdfListeMatchs[EN] / FeuilleListeMatchs[EN], PDF_GOTENBERG.md § 7.3): public (published
 * games) and admin (perimeter of the user; profile 7 as its Games page). Sorted by date, one section per day and venue.
 */
final class GamesListDocument implements PdfDocumentProvider
{
    public function __construct(
        private readonly GamesScope $scope,
        private readonly PdfLabels $labels,
    ) {
    }

    public function document(): string
    {
        return PdfCatalog::GAMES_LIST;
    }

    public function supports(PdfTheme $theme): bool
    {
        return true;
    }

    public function options(): array
    {
        return GamesScope::FILTER_OPTIONS;
    }

    public function content(PdfRequest $request, PdfTheme $theme, ?array $allowedClubs): ?PdfContent
    {
        $games = $this->scope->games($request, $theme);
        $header = $games === [] ? null : $this->scope->header($request, $theme, $games);
        if ($header === null) {
            return null;
        }
        $labels = $this->labels->forLanguage($request->lang);
        $public = $theme === PdfTheme::Public;
        $byDay = ($request->option('sort') ?? GamesSort::Date->value) === GamesSort::Date->value;

        $severalCompetitions = count(GamesScope::competitionLabels($games)) > 1;
        $sections = [];
        foreach ($games as $game) {
            $key = $byDay ? $game['Date_match'] . '|' . $game['Lieu'] : '';
            $sections[$key] ??= ['title' => $byDay ? RankingTable::gamedayLabel($game['Date_match'], $game['Lieu']) : null, 'rows' => []];
            $sections[$key]['rows'][] = self::row($game, $public);
        }

        return new PdfContent(
            template: 'sections.html.twig',
            title: $labels['games.list_title'],
            filename: sprintf('%s_%s_%s_%s.pdf', $this->document(), self::scopeName($request), $request->season, $request->lang),
            variables: [
                'competition' => $header['competition'],
                'columns' => self::columns($labels, $severalCompetitions),
                'sections' => array_values($sections),
                'dense' => true,
                'table_class' => 'pdf-games',
                'internal' => !$public,
            ],
            landscape: true,
            images: $header['images'],
        );
    }

    /**
     * Columns of the list: the competition only when the games belong to several; one column for both referees.
     *
     * @param array<string, string> $labels
     *
     * @return list<array{key: string, label: string, class: string}>
     */
    public static function columns(array $labels, bool $severalCompetitions): array
    {
        $columns = [
            ['key' => 'number', 'label' => $labels['player.number'], 'class' => 'num pdf-games__number'],
            ['key' => 'date', 'label' => $labels['games.date'], 'class' => 'pdf-games__date'],
            ['key' => 'time', 'label' => $labels['games.time'], 'class' => 'pdf-games__time'],
            ['key' => 'competition', 'label' => $labels['games.competition'], 'class' => 'pdf-games__competition'],
            ['key' => 'phase', 'label' => $labels['games.phase'], 'class' => 'pdf-games__phase'],
            ['key' => 'pitch', 'label' => $labels['games.pitch'], 'class' => 'pdf-games__pitch'],
            ['key' => 'home', 'label' => $labels['games.home'], 'class' => 'pdf-games__team'],
            ['key' => 'score', 'label' => $labels['games.score'], 'class' => 'pdf-games__score'],
            ['key' => 'away', 'label' => $labels['games.away'], 'class' => 'pdf-games__team'],
            ['key' => 'referees', 'label' => $labels['games.referees'], 'class' => 'pdf-games__referees'],
        ];

        return array_values(array_filter($columns, static fn (array $column): bool => $severalCompetitions || $column['key'] !== 'competition'));
    }

    /**
     * @param array<string, mixed> $game
     *
     * @return array<string, mixed>
     */
    public static function row(array $game, bool $public): array
    {
        $referee = static fn (?string $name): string => (string) ($public ? CompetitionRules::personName($name) : $name);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', (string) $game['Date_match']);
        $result = RankingTable::gameRow($game);

        return [
            'number' => (string) $game['Numero_ordre'],
            'date' => $date === false ? '' : $date->format('d/m'),
            'time' => substr((string) $game['Heure_match'], 0, 5),
            'competition' => GamesScope::competitionLabel($game),
            'phase' => trim(implode(' | ', array_filter([(string) $game['Phase'], (string) $game['Libelle']], 'strlen'))),
            'pitch' => (string) $game['Terrain'],
            'home' => $game['LibelleA'] ?? '',
            'score' => RankingTable::isPlayed($game) ? sprintf('%s - %s', $game['ScoreA'], $game['ScoreB']) : '',
            'away' => $game['LibelleB'] ?? '',
            // First referee above the second one.
            'referees' => implode("\n", array_filter([$referee($game['Arbitre_principal']), $referee($game['Arbitre_secondaire'])], 'strlen')),
            '_cells' => $result['_cells'],
        ];
    }

    private static function scopeName(PdfRequest $request): string
    {
        return match (true) {
            $request->event !== null => 'event-' . $request->event,
            $request->gameday !== null => 'gameday-' . $request->gameday,
            $request->competitions !== [] => implode('-', $request->competitions),
            default => 'games',
        };
    }
}
