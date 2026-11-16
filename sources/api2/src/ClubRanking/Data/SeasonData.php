<?php

namespace App\ClubRanking\Data;

use App\ClubRanking\NameNormalizer;

/**
 * Everything the calculation reads from KPI for one season, loaded once (SeasonDataLoader) so that the calculators
 * stay pure and testable without a database.
 */
final class SeasonData
{
    /** @var array<string, Club> */
    private array $clubs = [];
    /** @var array<int, Licensee> */
    private array $licensees = [];
    /** @var array<string, Competition> */
    private array $competitions = [];
    /** @var array<int, list<TeamPlayer>> */
    private array $playersByEntry = [];
    /** @var array<string, list<int>> */
    private array $matricsByName = [];

    /**
     * @param list<Club>               $clubs
     * @param list<Licensee>           $licensees
     * @param list<Competition>        $competitions
     * @param list<TeamEntry>          $teamEntries
     * @param list<TeamPlayer>         $teamPlayers
     * @param list<Gameday>            $gamedays
     * @param list<CompetitionManager> $competitionManagers
     * @param list<Referee>            $referees
     * @param array<int, list<string>> $ententes  team number → partner clubs
     * @param array<string, int>       $aliases   normalised name → matric (§ 7 « rapprochements »)
     * @param list<ManualEntry>        $manualEntries
     */
    public function __construct(
        public readonly string $season,
        array $clubs,
        array $licensees,
        array $competitions,
        public readonly array $teamEntries = [],
        array $teamPlayers = [],
        public readonly array $gamedays = [],
        public readonly array $competitionManagers = [],
        public readonly array $referees = [],
        public readonly array $ententes = [],
        public readonly array $aliases = [],
        public readonly array $manualEntries = [],
    ) {
        foreach ($clubs as $club) {
            $this->clubs[$club->code] = $club;
        }
        foreach ($licensees as $licensee) {
            $this->licensees[$licensee->matric] = $licensee;
            foreach ([$licensee->lastName . ' ' . $licensee->firstName, $licensee->firstName . ' ' . $licensee->lastName] as $name) {
                $key = NameNormalizer::normalize($name);
                if (!in_array($licensee->matric, $this->matricsByName[$key] ?? [], true)) {
                    $this->matricsByName[$key][] = $licensee->matric;
                }
            }
        }
        foreach ($competitions as $competition) {
            $this->competitions[$competition->code] = $competition;
        }
        foreach ($teamPlayers as $player) {
            $this->playersByEntry[$player->teamEntryId][] = $player;
        }
    }

    public function club(string $code): ?Club
    {
        return $this->clubs[$code] ?? null;
    }

    /** @return array<string, Club> */
    public function clubs(): array
    {
        return $this->clubs;
    }

    public function isEligibleClub(string $code): bool
    {
        return ($this->clubs[$code] ?? null)?->eligible ?? false;
    }

    public function licensee(int $matric): ?Licensee
    {
        return $this->licensees[$matric] ?? null;
    }

    public function competition(string $code): ?Competition
    {
        return $this->competitions[$code] ?? null;
    }

    /** @return list<Competition> */
    public function competitions(): array
    {
        return array_values($this->competitions);
    }

    /** @return list<TeamPlayer> */
    public function playersOf(int $teamEntryId): array
    {
        return $this->playersByEntry[$teamEntryId] ?? [];
    }

    /** @return list<int> licensees whose « NOM Prénom » or « Prénom NOM » is this name */
    public function matricsNamed(string $name): array
    {
        return $this->matricsByName[NameNormalizer::normalize($name)] ?? [];
    }

    public function alias(string $name): ?int
    {
        return $this->aliases[NameNormalizer::normalize($name)] ?? null;
    }

    /** @return list<string> */
    public function ententeClubs(int $teamNumber): array
    {
        return $this->ententes[$teamNumber] ?? [];
    }
}
