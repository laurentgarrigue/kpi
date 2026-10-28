<?php

namespace App\Pdf;

/**
 * Parameters of a PDF request (PDF_GOTENBERG.md § 6.1): the whole context is in the query, never in a session.
 * A parameter that is unknown, malformed or missing when required makes the request invalid.
 */
final class PdfRequest
{
    public const LANGUAGES = ['fr', 'en'];
    public const DEFAULT_TIMEZONE = 'Europe/Paris';

    /** Cap on the games of one document (§ 9). */
    public const MAX_GAMES = 200;

    private const SEASON = '/^\d{4}$/';
    private const CODE = '/^[A-Za-z0-9_-]{1,12}$/';
    private const OPTION_VALUE = '/^[A-Za-z0-9_-]{1,20}$/';
    private const COMMON = ['season', 'competition', 'event', 'team', 'gameday', 'games', 'lang', 'tz'];

    /**
     * @param list<int>             $games
     * @param array<string, string> $options document options (`view`, `visa`…)
     */
    private function __construct(
        public readonly string $season,
        public readonly ?string $competition,
        public readonly ?int $event,
        public readonly ?int $team,
        public readonly ?int $gameday,
        public readonly array $games,
        public readonly string $lang,
        public readonly string $timezone,
        public readonly array $options,
    ) {
    }

    /**
     * @param array<string, mixed> $query
     * @param list<string>         $allowedOptions options the document understands
     *
     * @throws InvalidPdfRequest
     */
    public static function fromQuery(array $query, array $allowedOptions = []): self
    {
        foreach ($query as $name => $value) {
            if (!is_string($value)) {
                throw new InvalidPdfRequest(sprintf('Parameter "%s" must be a single value.', $name));
            }
            if (!in_array($name, self::COMMON, true) && !in_array($name, $allowedOptions, true)) {
                throw new InvalidPdfRequest(sprintf('Unknown parameter "%s".', $name));
            }
        }

        $season = $query['season'] ?? null;
        if ($season === null || !preg_match(self::SEASON, $season)) {
            throw new InvalidPdfRequest('Parameter "season" (YYYY) is required.');
        }

        $options = [];
        foreach ($allowedOptions as $name) {
            if (isset($query[$name])) {
                $options[$name] = self::matching($query, $name, self::OPTION_VALUE);
            }
        }

        $lang = $query['lang'] ?? 'fr';
        if (!in_array($lang, self::LANGUAGES, true)) {
            throw new InvalidPdfRequest('Parameter "lang" must be "fr" or "en".');
        }

        $timezone = $query['tz'] ?? self::DEFAULT_TIMEZONE;
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            throw new InvalidPdfRequest('Parameter "tz" must be an IANA time zone.');
        }

        return new self(
            $season,
            isset($query['competition']) ? self::matching($query, 'competition', self::CODE) : null,
            self::positiveInt($query, 'event'),
            self::positiveInt($query, 'team'),
            self::positiveInt($query, 'gameday'),
            self::games($query['games'] ?? null),
            $lang,
            $timezone,
            $options,
        );
    }

    public function requireCompetition(): string
    {
        return $this->competition ?? throw new InvalidPdfRequest('Parameter "competition" is required.');
    }

    public function option(string $name): ?string
    {
        return $this->options[$name] ?? null;
    }

    /** @return array<string, string|int> the parameters, to sign a link or to build a cache key */
    public function toQuery(): array
    {
        return array_filter([
            'season' => $this->season,
            'competition' => $this->competition,
            'event' => $this->event,
            'team' => $this->team,
            'gameday' => $this->gameday,
            'games' => $this->games === [] ? null : implode(',', $this->games),
            'lang' => $this->lang,
            'tz' => $this->timezone,
            ...$this->options,
        ], static fn ($value) => $value !== null);
    }

    /** @param array<string, string> $query */
    private static function matching(array $query, string $name, string $pattern): string
    {
        if (!preg_match($pattern, $query[$name])) {
            throw new InvalidPdfRequest(sprintf('Parameter "%s" is malformed.', $name));
        }

        return $query[$name];
    }

    /** @param array<string, string> $query */
    private static function positiveInt(array $query, string $name): ?int
    {
        if (!isset($query[$name])) {
            return null;
        }
        $value = filter_var($query[$name], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new InvalidPdfRequest(sprintf('Parameter "%s" must be a positive integer.', $name));
        }

        return $value;
    }

    /** @return list<int> */
    private static function games(?string $list): array
    {
        if ($list === null) {
            return [];
        }
        $games = [];
        foreach (explode(',', $list) as $id) {
            $value = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($value === false) {
                throw new InvalidPdfRequest('Parameter "games" must be a comma-separated list of game ids.');
            }
            $games[] = $value;
        }
        if (count($games) > self::MAX_GAMES) {
            throw new InvalidPdfRequest(sprintf('At most %d games per document.', self::MAX_GAMES));
        }

        return array_values(array_unique($games));
    }
}
