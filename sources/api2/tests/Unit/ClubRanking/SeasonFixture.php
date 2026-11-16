<?php

namespace App\Tests\Unit\ClubRanking;

use App\ClubRanking\Calculator\CalculationContext;
use App\ClubRanking\Data\Club;
use App\ClubRanking\Data\Competition;
use App\ClubRanking\Data\CompetitionManager;
use App\ClubRanking\Data\Gameday;
use App\ClubRanking\Data\Licensee;
use App\ClubRanking\Data\ManualEntry;
use App\ClubRanking\Data\Referee;
use App\ClubRanking\Data\SeasonData;
use App\ClubRanking\Data\TeamEntry;
use App\ClubRanking\Data\TeamPlayer;
use App\ClubRanking\Model\Category;
use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\Scale;

/** Small season to test the calculators: clubs, teams, players, gamedays… built fluently. */
final class SeasonFixture
{
    public const SEASON = '2026';

    /** @var list<Club> */
    private array $clubs = [];
    /** @var list<Licensee> */
    private array $licensees = [];
    /** @var list<Competition> */
    private array $competitions = [];
    /** @var list<TeamEntry> */
    private array $entries = [];
    /** @var list<TeamPlayer> */
    private array $players = [];
    /** @var list<Gameday> */
    private array $gamedays = [];
    /** @var list<CompetitionManager> */
    private array $managers = [];
    /** @var list<Referee> */
    private array $referees = [];
    /** @var array<int, list<string>> */
    private array $ententes = [];
    /** @var array<string, int> */
    private array $aliases = [];
    /** @var list<ManualEntry> */
    private array $manual = [];
    /** @var list<Category> */
    private array $categories = [];
    private int $nextEntry = 1;

    public static function create(): self
    {
        return (new self())
            ->club('5006', 'Avranches')
            ->club('3512', 'Acigné')
            ->club('1412', 'Thury-Harcourt')
            ->club('4421', 'Nantes')
            ->club('CR04', 'Sélection Normandie', false)
            ->club('ESP03', 'Madrid', false);
    }

    public function club(string $code, string $label, bool $eligible = true): self
    {
        $this->clubs[] = new Club($code, $label, $eligible, '50 - Manche', 'Normandie');

        return $this;
    }

    public function licensee(int $matric, string $club, string $lastSeason = self::SEASON, string $last = 'NOM', string $first = 'Prénom', bool $foreign = false): self
    {
        $this->licensees[] = new Licensee($matric, $last . $matric, $first, $club, $lastSeason, $foreign);

        return $this;
    }

    public function named(int $matric, string $club, string $last, string $first): self
    {
        $this->licensees[] = new Licensee($matric, $last, $first, $club, self::SEASON);

        return $this;
    }

    public function competition(string $code, string $type = 'CHPT', bool $published = true, bool $locked = true, string $level = 'NAT'): self
    {
        $this->competitions[] = new Competition($code, $code, $type, $level, $published, $locked);

        return $this;
    }

    public function category(string $code, array $entryCodes, ?string $final = null, ?string $regular = null): self
    {
        $this->categories[] = new Category($code, $code, $entryCodes, $final, $regular);

        return $this;
    }

    /**
     * Engages a team in a competition; returns the id of the entry through $entryId.
     *
     * @param array<int, bool> $players matric → played at least one validated game
     */
    public function team(int $teamNumber, string $club, string $competition, array $players = [], int $rank = 0, int $levelRank = 0, int $games = 3, ?int &$entryId = null, array $coaches = []): self
    {
        $entryId = $this->nextEntry++;
        $this->entries[] = new TeamEntry($entryId, $competition, $teamNumber, $club, 'Équipe ' . $teamNumber, $rank, $levelRank, $games);
        foreach ($players as $matric => $played) {
            $this->players[] = new TeamPlayer($entryId, $matric, $played);
        }
        foreach ($coaches as $matric) {
            $this->players[] = new TeamPlayer($entryId, $matric, true, true);
        }

        return $this;
    }

    public function entente(int $teamNumber, string $club): self
    {
        $this->ententes[$teamNumber][] = $club;

        return $this;
    }

    public function gameday(int $id, string $competition, string $date, string $place, string $organiser, string $delegate = '', string $chief = '', ?string $end = null): self
    {
        $this->gamedays[] = new Gameday($id, $competition, $date, $end ?? $date, $place, $organiser, '', $delegate, $chief);

        return $this;
    }

    public function manager(int $matric, ?string $competition): self
    {
        $this->managers[] = new CompetitionManager($matric, $competition);

        return $this;
    }

    public function referee(int $matric, string $qualification, string $level = '', int $nationalGames = 0): self
    {
        $this->referees[] = new Referee($matric, $qualification, $level, $nationalGames);

        return $this;
    }

    public function alias(string $name, int $matric): self
    {
        $this->aliases[\App\ClubRanking\NameNormalizer::normalize($name)] = $matric;

        return $this;
    }

    public function manual(string $rule, ?int $matric, ?string $club = null, int $id = 0): self
    {
        $this->manual[] = new ManualEntry($id ?: count($this->manual) + 1, $rule, $matric, $club);

        return $this;
    }

    public function data(): SeasonData
    {
        return new SeasonData(self::SEASON, $this->clubs, $this->licensees, $this->competitions, $this->entries, $this->players,
            $this->gamedays, $this->managers, $this->referees, $this->ententes, $this->aliases, $this->manual);
    }

    /** @param list<Rule> $rules */
    public function scale(array $rules = []): Scale
    {
        return new Scale(self::SEASON, $rules, $this->categories);
    }

    /**
     * @param list<Rule> $rules
     * @param list<Line> $lines
     */
    public function context(array $rules = [], array $lines = []): CalculationContext
    {
        return new CalculationContext($this->scale($rules), $this->data(), $lines);
    }
}
