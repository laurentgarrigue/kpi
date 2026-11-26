<?php

namespace App\Tests\Unit\Designation;

use App\Designation\Facts\Fact;
use App\Designation\Facts\FactsAggregator;
use PHPUnit\Framework\TestCase;

/** Relevé des faits (FEATURE_DESIGNATIONS.md REL-01, REL-03, REL-04). */
final class FactsAggregatorTest extends TestCase
{
    public function testRel03FactsAddUpByClubFactAndQualifierKeepingEachSource(): void
    {
        $rows = FactsAggregator::rows([
            new Fact('C001', Fact::OFFICIAL_PROVIDED, 'DELEGUE_CNA', 1, 'NDES J1', 'JOURNEE', '9801'),
            new Fact('C001', Fact::OFFICIAL_PROVIDED, 'DELEGUE_CNA', 1, 'NDES J2', 'JOURNEE', '9802'),
            new Fact('C001', Fact::OFFICIAL_PROVIDED, 'JUGE_ARBITRE', 1, 'NDES J1', 'JOURNEE', '9801'),
        ], ['C001' => 'Club Un']);

        self::assertCount(2, $rows);
        self::assertSame(['C001', 'Club Un', 2], [$rows[0]['club'], $rows[0]['clubLabel'], $rows[0]['quantity']]);
        self::assertSame(['JOURNEE', '9802'], [$rows[0]['details'][1]['sourceType'], $rows[0]['details'][1]['sourceId']]);
    }

    public function testRel01OnlyQuantitiesAndNothingForAZeroQuantity(): void
    {
        $rows = FactsAggregator::rows([
            new Fact('C002', Fact::GAMEDAY_PITCHES, 'NDES', 0, 'J1', 'JOURNEE', '9801'),
            new Fact(null, Fact::INFRACTION, '2', 1, 'Club X', 'REPORT', '12'),
            new Fact('C001', Fact::CARD_YELLOW, '', 3, 'Match 1', 'MATCH', '9801'),
        ], []);

        self::assertSame([['C001', 'card_yellow'], [null, 'infraction']], array_map(static fn (array $r): array => [$r['club'], $r['fact']], $rows));
        foreach ($rows as $row) {
            self::assertSame(['club', 'clubLabel', 'fact', 'qualifier', 'quantity', 'details'], array_keys($row));
        }
    }
}
