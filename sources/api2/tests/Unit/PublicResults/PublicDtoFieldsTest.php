<?php

namespace App\Tests\Unit\PublicResults;

use App\PublicResults\Dto\CompetitionHeader;
use App\PublicResults\Dto\CompetitionSummary;
use App\PublicResults\Dto\LinkedEvent;
use App\PublicResults\Dto\RankingRow;
use App\PublicResults\Dto\TeamRef;
use PHPUnit\Framework\TestCase;

/**
 * API-10 : chaque DTO public expose EXACTEMENT ces champs. Ajouter un champ (donc une donnée publiée) oblige à
 * modifier ce test, et donc à le justifier en revue (minimisation des données, stratégie § 11).
 */
final class PublicDtoFieldsTest extends TestCase
{
    public function testTeamRefFields(): void
    {
        self::assertSame(['id', 'number', 'label', 'logo'], array_keys((new TeamRef(1, 10, 'Equipe', null))->jsonSerialize()));
    }

    public function testRankingRowFields(): void
    {
        $team = new TeamRef(1, 10, 'Equipe', null);
        self::assertSame(['rank', 'team', 'points', 'played', 'medal'], array_keys((new RankingRow(1, $team, 7, 3, 1))->jsonSerialize()));

        $details = ['won' => 2, 'drawn' => 1, 'lost' => 0, 'forfeits' => 0, 'goals_for' => 9, 'goals_against' => 4, 'goal_diff' => 5];
        self::assertSame(
            ['rank', 'team', 'points', 'played', 'won', 'drawn', 'lost', 'forfeits', 'goals_for', 'goals_against', 'goal_diff', 'medal'],
            array_keys((new RankingRow(1, $team, 7, 3, null, $details))->jsonSerialize()),
        );
    }

    public function testLinkedEventFields(): void
    {
        self::assertSame(
            ['id', 'libelle', 'place', 'start', 'end', 'logo', 'share'],
            array_keys((new LinkedEvent(77, 'Tournoi', 'Lieu', '2026-01-01', '2026-01-02', null, 0.5))->jsonSerialize()),
        );
    }

    public function testCompetitionSummaryFields(): void
    {
        $summary = new CompetitionSummary('N1H', '2026', 'Nationale 1', 'Poule A');
        self::assertSame(['code', 'display_title', 'soustitre2'], array_keys($summary->jsonSerialize()));
        self::assertSame(['code', 'season', 'display_title', 'soustitre2'], array_keys($summary->withSeason()));
    }

    public function testCompetitionHeaderFields(): void
    {
        $header = new CompetitionHeader(
            'N1H', '2026', ['code' => 'N1', 'libelle' => 'Nationale 1', 'libelle_en' => null], 'Nationale 1', null, null,
            'Nationale 1', 'CHPT', 'ON', 'NAT', null, null, null, 3, 0, true, 1, false,
        );
        self::assertSame([
            'code', 'season', 'group', 'libelle', 'soustitre', 'soustitre2', 'display_title', 'type', 'status', 'level',
            'banner', 'logo', 'web', 'qualified', 'eliminated', 'has_games', 'round', 'final',
        ], array_keys($header->jsonSerialize()));
    }
}
