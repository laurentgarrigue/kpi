<?php

namespace App\Tests\Unit\Pdf\Scoresheet;

use App\Pdf\Scoresheet\ScoresheetSheet;
use PHPUnit\Framework\TestCase;

/** Scoresheet (PDF_GOTENBERG.md § 7.3.3): same rules as the legacy FeuilleMatchMulti.php. */
final class ScoresheetSheetTest extends TestCase
{
    private const LABELS = [
        'role.C' => 'Capitaine', 'role.E' => 'Staff', 'scoresheet.team' => 'Équipe', 'scoresheet.definitive' => 'D',
        'scoresheet.reason_r_o' => 'Obstruction',
    ];

    public function testRosterMarksCoachCaptainLicenceAndPaddedLines(): void
    {
        $roster = ScoresheetSheet::roster([
            self::player(7, 'C', 123456, '2025', 123456),
            self::player(8, '-', 123457, '2026', null),
            self::player(null, 'E', 2000001, '2026', 2000001),
        ], '2026', false, self::LABELS);

        self::assertCount(ScoresheetSheet::ROSTER_LINES, $roster);
        self::assertSame(['7', 'DUPONT (Capitaine)', '123456 (2025)', 'SEN'], [$roster[0]['number'], $roster[0]['last_name'], $roster[0]['licence'], $roster[0]['category']], 'the captain by the label of its role');
        self::assertSame('SEN(sup)', $roster[1]['category'], 'not in the team roster');
        self::assertSame(['Staff', 'DUPONT', ''], [$roster[2]['number'], $roster[2]['last_name'], $roster[2]['licence']], '« Staff » in place of the number');
        self::assertCount(ScoresheetSheet::INTERNATIONAL_ROSTER_LINES, ScoresheetSheet::roster([], '2026', true, self::LABELS));
    }

    public function testEventsBeyondTheSheetContinueOnTheNextPages(): void
    {
        $line = static fn (int $i): array => ['time' => 'M1 - ' . $i];

        $pages = ScoresheetSheet::eventPages(array_map($line, range(1, ScoresheetSheet::EVENT_LINES + 3)));

        self::assertCount(2, $pages);
        self::assertCount(ScoresheetSheet::EVENT_LINES, $pages[0]);
        self::assertSame(['time' => 'M1 - 27'], $pages[1][0], 'the 27th event opens the continuation page');
        self::assertCount(ScoresheetSheet::CONTINUATION_EVENT_LINES, $pages[1], 'padded to be filled in by hand');
        self::assertSame([], $pages[1][3]);
        self::assertCount(1, ScoresheetSheet::eventPages(array_map($line, range(1, ScoresheetSheet::EVENT_LINES))), 'a full sheet, no continuation');
        self::assertCount(3, ScoresheetSheet::eventPages(array_map($line, range(1, ScoresheetSheet::EVENT_LINES + ScoresheetSheet::CONTINUATION_EVENT_LINES + 1))));
    }

    public function testTheQrCodeLeadsToTheScoringPageOfTheGameInApp4(): void
    {
        self::assertSame('https://kayak-polo.info/admin2/games/9401/scoring', ScoresheetSheet::scoringUrl('https://kayak-polo.info/admin2/', 9401));
    }

    public function testCategoryByAgeInTheSeason(): void
    {
        self::assertSame('MIN', ScoresheetSheet::category('2012-06-01', '2026'));
        self::assertSame('SEN', ScoresheetSheet::category('2000-01-01', '2026'));
        self::assertSame('V2', ScoresheetSheet::category('1984-01-01', '2026'));
        self::assertSame('', ScoresheetSheet::category(null, '2026'));
        self::assertSame('', ScoresheetSheet::category('2020-01-01', '2026'), 'too young');
    }

    public function testEventGridCountsTheGoalsAndTheHalfTime(): void
    {
        $grid = ScoresheetSheet::events([
            self::event('M1', '00:07:30', 'B', 'A', 'MARTIN', 'Jean-Paul'),
            self::event('M1', '00:04:10', 'J', 'B', 'DURAND', 'Luc', 'r_o'),
            self::event('M2', '00:00:00', 'B', 'B', null, null),
            self::event('M2', '00:02:00', 'D', 'A', 'MARTIN', 'Jean-Paul'),
        ], self::LABELS);

        self::assertCount(1, $grid['pages'], 'one page when the events fit');
        self::assertCount(ScoresheetSheet::EVENT_LINES, $grid['pages'][0]);
        $grid['lines'] = $grid['pages'][0];
        self::assertSame(['time' => 'M1 - 07:30', 'a_player' => '7 - MAR. J-P.', 'a_goal' => 'X'], $grid['lines'][0]);
        self::assertSame('7 - DUR. L. (Obstruction)', $grid['lines'][1]['b_player']);
        self::assertSame('X', $grid['lines'][1]['b_yellow']);
        self::assertSame(['time' => 'M2 - ', 'b_player' => 'Équipe B', 'b_goal' => 'X'], $grid['lines'][2]);
        self::assertSame('X', $grid['lines'][3]['a_definitive'], 'the definitive red card has its own column');
        self::assertArrayNotHasKey('a_red', $grid['lines'][3]);
        self::assertSame(['A' => 1, 'B' => 1], $grid['goals']);
        self::assertSame(['A' => 1, 'B' => 0], $grid['half_time']);
        self::assertTrue(ScoresheetSheet::isProvisional($grid['goals'], '2', '1'));
        self::assertFalse(ScoresheetSheet::isProvisional($grid['goals'], '1', '1'));
    }

    public function testRefereesAndScores(): void
    {
        self::assertSame('MARTIN Jean', ScoresheetSheet::referee('MARTIN Jean NAT-B'));
        self::assertSame('', ScoresheetSheet::referee('-1'));
        self::assertSame('', ScoresheetSheet::score('?'));
        self::assertSame('3', ScoresheetSheet::score('3'));
    }

    /** @return array<string, mixed> */
    private static function player(?int $number, string $role, int $matric, string $origin, ?int $titular): array
    {
        return ['Numero' => $number, 'Capitaine' => $role, 'Matric' => $matric, 'Nom' => 'Dupont', 'Prenom' => 'jean', 'Naissance' => '2000-01-01', 'Origine' => $origin, 'icf' => null, 'titular' => $titular];
    }

    /** @return array<string, mixed> */
    private static function event(string $period, string $time, string $type, string $side, ?string $lastName, ?string $firstName, ?string $reason = null): array
    {
        return ['Periode' => $period, 'Temps' => $time, 'Id_evt_match' => $type, 'Equipe_A_B' => $side, 'Numero' => '7', 'Nom' => $lastName, 'Prenom' => $firstName, 'motif' => $reason];
    }
}
