<?php

namespace App\Tests\Unit\PublicResults;

use App\PublicResults\TeamLogos;
use App\PublicSite\MediaFiles;
use PHPUnit\Framework\TestCase;

/** Logo d'équipe absent : repli sur le logo du club, puis le drapeau de la nation, puis le logo vide. */
final class TeamLogosTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kpi-logos-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/img/KIP/logo', 0777, true);
        mkdir($this->root . '/img/Nations', 0777, true);
        touch($this->root . '/img/KIP/logo/5006-logo.png');
        touch($this->root . '/img/Nations/ESP.png');
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testMissingTeamLogoFallsBackToTheClubLogoThenTheNationFlagThenTheEmptyLogo(): void
    {
        $rows = (new TeamLogos(new MediaFiles($this->root)))->fill([
            ['t_club' => '5006', 't_logo' => TeamLogos::EMPTY_LOGO],
            ['t_club' => 'ESP01', 't_logo' => ''],
            ['t_club' => '9999', 't_logo' => TeamLogos::EMPTY_LOGO],
        ], 't_');

        self::assertSame(['KIP/logo/5006-logo.png', 'Nations/ESP.png', TeamLogos::EMPTY_LOGO], array_column($rows, 't_logo'));
    }

    public function testATeamLogoAlreadySetIsKept(): void
    {
        $rows = (new TeamLogos(new MediaFiles($this->root)))->fill([['t_club' => '5006', 't_logo' => 'KIP/logo/custom.png']], 't_');

        self::assertSame('KIP/logo/custom.png', $rows[0]['t_logo']);
    }

    public function testBothSidesOfAGameAreFilledAndAnUnknownTeamIsLeftAlone(): void
    {
        $rows = (new TeamLogos(new MediaFiles($this->root)))->fill([
            ['t_a_club' => '5006', 't_a_logo' => TeamLogos::EMPTY_LOGO, 't_b_club' => null, 't_b_logo' => TeamLogos::EMPTY_LOGO],
        ], 't_a_', 't_b_');

        self::assertSame('KIP/logo/5006-logo.png', $rows[0]['t_a_logo']);
        self::assertSame(TeamLogos::EMPTY_LOGO, $rows[0]['t_b_logo']);
    }
}
