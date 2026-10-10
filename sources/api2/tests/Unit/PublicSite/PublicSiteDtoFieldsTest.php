<?php

namespace App\Tests\Unit\PublicSite;

use App\PublicSite\Dto\CalendarEntry;
use App\PublicSite\Dto\ClubSheet;
use App\PublicSite\Dto\ClubSummary;
use App\PublicSite\Dto\Honour;
use App\PublicSite\Dto\PodiumTeam;
use App\PublicSite\Dto\RosterPlayer;
use App\PublicSite\Dto\SearchCompetition;
use App\PublicSite\Dto\SearchEvent;
use App\PublicSite\Dto\TeamSheet;
use App\PublicSite\Dto\TeamSummary;
use PHPUnit\Framework\TestCase;

/**
 * API3-09 : chaque DTO public de la phase 3 expose EXACTEMENT ces champs. Ajouter un champ (donc une donnée
 * publiée) oblige à modifier ce test, et donc à le justifier en revue (minimisation, stratégie § 11).
 */
final class PublicSiteDtoFieldsTest extends TestCase
{
    public function testCalendarEntryFields(): void
    {
        $competition = ['season' => '2026', 'code' => 'N1H', 'display_title' => 'N1', 'type' => 'CHPT', 'section' => 2, 'group' => 'N1H'];
        $entry = new CalendarEntry(1, $competition, 'J1 - Lieu (33)', 'J1', 'Lieu', '33', '2026-01-01', '2026-01-02', null);

        self::assertSame(['id', 'competition', 'label', 'name', 'place', 'department', 'start', 'end', 'event'], array_keys($entry->jsonSerialize()));
    }

    public function testTeamFields(): void
    {
        $team = new TeamSummary(101, 'Equipe', 'C001', 'Club');
        self::assertSame(['number', 'label', 'club'], array_keys($team->jsonSerialize()));

        $sheet = new TeamSheet($team, null, null, null, [], []);
        self::assertSame(['number', 'label', 'club', 'logo', 'colors', 'photo', 'honours', 'seasons'], array_keys($sheet->jsonSerialize()));

        self::assertSame(['season', 'competition', 'rank', 'medal', 'final_round'], array_keys((new Honour('2026', 'N1H', 'N1', 'N1H', 1, 1, true))->jsonSerialize()));
    }

    public function testRosterPlayerHasNoLicenceSexNorBirthDate(): void
    {
        $player = new RosterPlayer('Ann', 'ALPHA', 7, 'SEN', 'captain', 2, 0, 0, 0, 0);

        self::assertSame(
            ['first_name', 'last_name', 'number', 'category', 'role', 'goals', 'green', 'yellow', 'red', 'red_final'],
            array_keys($player->jsonSerialize()),
        );
    }

    public function testPodiumTeamFields(): void
    {
        $podium = (new PodiumTeam(1, 101, 'Equipe', null, 1))->jsonSerialize();

        self::assertSame(['rank', 'team', 'medal'], array_keys($podium));
        self::assertSame(['number', 'label', 'logo'], array_keys($podium['team']));
    }

    public function testClubFields(): void
    {
        $club = new ClubSummary('C001', 'Club', 'CD33', 'Gironde', null, null);
        self::assertSame(['code', 'label', 'department', 'logo', 'position'], array_keys($club->jsonSerialize()));

        $sheet = new ClubSheet($club, ['code' => 'CR', 'label' => 'Région'], null, null, null, []);
        self::assertSame(
            ['code', 'label', 'department', 'region', 'www', 'email', 'postal', 'position', 'logo', 'teams'],
            array_keys($sheet->jsonSerialize()),
        );
    }

    public function testSearchFields(): void
    {
        self::assertSame(['season', 'code', 'display_title', 'soustitre2', 'group'], array_keys((new SearchCompetition('2026', 'N1H', 'N1', null, 'N1H'))->jsonSerialize()));
        self::assertSame(['id', 'libelle', 'place', 'start', 'end'], array_keys((new SearchEvent(1, 'Tournoi', null, null, null))->jsonSerialize()));
    }
}
