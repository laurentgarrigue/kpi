<?php

namespace App\ClubRanking\Calculator;

use App\ClubRanking\Data\SeasonData;
use App\ClubRanking\Data\TeamEntry;
use App\ClubRanking\Model\Category;
use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Scale;

/**
 * What a calculator reads: the scale, the season data, and the lines already computed by the rules before it (the
 * manual lists run last, for the exclusions of 7.3.4).
 */
final class CalculationContext
{
    /** Licences outside the FFCK (manual entries, imports) are numbered from 2 000 000 (PAGE_ATHLETES.md Q7). */
    public const FIRST_FOREIGN_MATRIC = 2000000;

    /** @param list<Line> $previousLines */
    public function __construct(
        public readonly Scale $scale,
        public readonly SeasonData $data,
        public readonly array $previousLines = [],
    ) {
    }

    /** @param list<Line> $lines */
    public function withLines(array $lines): self
    {
        return new self($this->scale, $this->data, $lines);
    }

    /**
     * Club that earns the points of a licensee (§ 5.1, § 5.3): his club at the reference date, if he is licensed for
     * the season, French, and his club is eligible. Null otherwise.
     */
    public function clubOfLicensee(int $matric): ?string
    {
        $licensee = $this->data->licensee($matric);
        if ($licensee === null || $licensee->foreign || $matric >= self::FIRST_FOREIGN_MATRIC
            || !$licensee->isLicensedFor($this->data->season)) {
            return null;
        }

        return $this->data->isEligibleClub($licensee->clubCode) ? $licensee->clubCode : null;
    }

    /**
     * The teams of a category, each with all its engagements in the category (§ 5.4).
     *
     * @return array<int, list<TeamEntry>> team number → its entries
     */
    public function teamsOf(Category $category): array
    {
        $teams = [];
        foreach ($this->data->teamEntries as $entry) {
            if ($category->covers($entry->competition)) {
                $teams[$entry->teamNumber][] = $entry;
            }
        }
        ksort($teams);

        return $teams;
    }

    /**
     * Licensees that make a club « have a licensee in the team » (§ 5.5): players who played at least one validated
     * game for the team (default) or players of the roster; never the coaches.
     *
     * @param list<TeamEntry> $entries
     *
     * @return list<int>
     */
    public function playersOf(array $entries, bool $rosterIsEnough = false): array
    {
        $matrics = [];
        foreach ($entries as $entry) {
            foreach ($this->data->playersOf($entry->id) as $player) {
                if (!$player->coach && ($player->played || ($rosterIsEnough && $player->onRoster))) {
                    $matrics[$player->matric] = true;
                }
            }
        }

        return array_keys($matrics);
    }

    /**
     * Clubs of the players of a team, each once (§ 4.4).
     *
     * @param list<TeamEntry> $entries
     *
     * @return list<string>
     */
    public function clubsOfPlayers(array $entries, bool $rosterIsEnough = false): array
    {
        $clubs = [];
        foreach ($this->playersOf($entries, $rosterIsEnough) as $matric) {
            $club = $this->clubOfLicensee($matric);
            if ($club !== null) {
                $clubs[$club] = true;
            }
        }
        $codes = array_map('strval', array_keys($clubs));
        sort($codes);

        return $codes;
    }

    /**
     * The club of a team and the partner clubs of an entente, eligible ones only (§ 5.3).
     *
     * @param list<TeamEntry> $entries
     *
     * @return list<string>
     */
    public function clubsOfTeam(array $entries, bool $withEntente = true): array
    {
        $clubs = [$entries[0]->clubCode];
        if ($withEntente) {
            $clubs = [...$clubs, ...$this->data->ententeClubs($entries[0]->teamNumber)];
        }
        $clubs = array_values(array_unique(array_filter($clubs, fn (string $club): bool => $this->data->isEligibleClub($club))));
        sort($clubs);

        return $clubs;
    }

    /** @param list<TeamEntry> $entries */
    public static function validatedGames(array $entries): int
    {
        return array_sum(array_map(static fn (TeamEntry $entry): int => $entry->validatedGames, $entries));
    }
}
