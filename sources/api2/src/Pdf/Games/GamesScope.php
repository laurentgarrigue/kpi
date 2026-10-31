<?php

namespace App\Pdf\Games;

use App\Pdf\InvalidPdfRequest;
use App\Pdf\PdfAccessChecker;
use App\Pdf\PdfCompetitions;
use App\Pdf\PdfRequest;
use App\Pdf\PdfTheme;

/**
 * Games selected by a request of a game document (PDF_GOTENBERG.md § 7.3) and the header of the document: competitions
 * of the season, an event, a gameday or a list of games, the filters of the Games page of app4 as options, and the
 * visibility of the audience (public: published only; profile 7: as its Games page; others: their perimeter).
 */
class GamesScope
{
    /** Filters of the Games page, as document options. */
    public const FILTER_OPTIONS = ['date', 'pitch', 'round', 'unlocked', 'sort'];

    private const DATE = '/^\d{4}-\d{2}-\d{2}$/';

    public function __construct(
        private readonly GamesRepository $games,
        private readonly PdfCompetitions $competitions,
        private readonly PdfAccessChecker $access,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws InvalidPdfRequest
     */
    public function games(PdfRequest $request, PdfTheme $theme, ?GamesSort $sort = null): array
    {
        if ($request->competitions === [] && $request->event === null && $request->gameday === null && $request->games === []) {
            throw new InvalidPdfRequest('One of "competition", "event", "gameday" or "games" is required.');
        }
        $date = $request->option('date');
        if ($date !== null && !preg_match(self::DATE, $date)) {
            throw new InvalidPdfRequest('Parameter "date" must be YYYY-MM-DD.');
        }
        $requestedSort = $request->option('sort');
        $sort ??= $requestedSort === null ? GamesSort::Date : (GamesSort::tryFrom($requestedSort)
            ?? throw new InvalidPdfRequest('Parameter "sort" must be one of date, competition, phase, pitch, number.'));
        $admin = $theme === PdfTheme::Admin;

        return $this->games->games(new GamesQuery(
            season: $request->season,
            competitions: $request->competitions,
            event: $request->event,
            gameday: $request->gameday,
            games: $request->games,
            date: $date,
            pitch: $request->option('pitch'),
            round: $request->option('round'),
            unlockedOnly: $request->option('unlocked') === '1',
            sort: $sort,
            visibility: match (true) {
                !$admin => GamesVisibility::Public,
                $this->access->publishedGamesOnly() => GamesVisibility::Team,
                default => GamesVisibility::All,
            },
            allowedCompetitions: $admin ? $this->access->allowedCompetitions() : null,
            allowedGamedays: $admin ? $this->access->allowedGamedays() : null,
        ));
    }

    /**
     * Header of the document: the competition when there is only one, else the event, else the season.
     *
     * @param list<array<string, mixed>> $games
     *
     * @return array{competition: array<string, mixed>, images: array<string, string>}|null null for an unpublished
     *                                                                                    event or competition (public)
     */
    public function header(PdfRequest $request, PdfTheme $theme, array $games): ?array
    {
        $codes = array_values(array_unique(array_column($games, 'Code_competition')));
        if ($request->event !== null) {
            $event = $this->games->event($request->event);
            if ($event === null || ($theme === PdfTheme::Public && !$event['published'])) {
                return null;
            }

            return ['competition' => ['code' => '', 'title' => $event['title'], 'subtitle' => $event['place'] ?: null, 'logo' => null], 'images' => []];
        }
        if (count($codes) === 1) {
            $header = $theme === PdfTheme::Public
                ? $this->competitions->publishedHeader($request->season, $codes[0])
                : $this->competitions->header($request->season, $codes[0]);

            return $header === null ? null : ['competition' => $header['competition'], 'images' => $header['images']];
        }

        return ['competition' => ['code' => '', 'title' => implode(', ', $codes), 'subtitle' => null, 'logo' => null], 'images' => []];
    }
}
