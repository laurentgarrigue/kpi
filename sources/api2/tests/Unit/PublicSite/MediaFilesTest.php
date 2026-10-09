<?php

namespace App\Tests\Unit\PublicSite;

use App\PublicSite\MediaFiles;
use PHPUnit\Framework\TestCase;

/** Visuels des clubs et des équipes : règles de kpclassements.php et kpequipes.php (Q-P3-3). */
final class MediaFilesTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/kpi-media-' . bin2hex(random_bytes(4));
        foreach (['KIP/logo', 'Nations', 'KIP/colors', 'KIP/teams'] as $dir) {
            mkdir($this->root . '/img/' . $dir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        exec('rm -rf ' . escapeshellarg($this->root));
    }

    public function testApi07ClubLogoThenNationFlagThenNull(): void
    {
        $this->touch('KIP/logo/C001-logo.png');
        $this->touch('Nations/ESP.png');
        $media = new MediaFiles($this->root);

        self::assertSame('KIP/logo/C001-logo.png', $media->clubLogo('C001'));
        self::assertSame('Nations/ESP.png', $media->clubLogo('ESP01'));
        self::assertNull($media->clubLogo('C002'));
    }

    public function testTeamColorsTakeTheMostRecentYearWithinThreeYearsThenTheUndatedFile(): void
    {
        $media = new MediaFiles($this->root);
        self::assertNull($media->teamColors(101, 2026));

        $this->touch('KIP/colors/101-colors.png');
        self::assertSame(['image' => 'KIP/colors/101-colors.png', 'season' => null], $media->teamColors(101, 2026));

        $this->touch('KIP/colors/101-2022-colors.png');
        self::assertSame(['image' => 'KIP/colors/101-colors.png', 'season' => null], $media->teamColors(101, 2026), '2022 : au-delà de 3 ans');

        $this->touch('KIP/colors/101-2024-colors.png');
        self::assertSame(['image' => 'KIP/colors/101-2024-colors.png', 'season' => '2024'], $media->teamColors(101, 2026));
    }

    public function testTeamPhotoIsKeptUntilTheGdprStudyWithinFiveYears(): void
    {
        $media = new MediaFiles($this->root);
        $this->touch('KIP/teams/101-2020-team.jpg');
        self::assertNull($media->teamPhoto(101, 2026), '2020 : au-delà de 5 ans');

        $this->touch('KIP/teams/101-2021-team.jpg');
        self::assertSame(['image' => 'KIP/teams/101-2021-team.jpg', 'season' => '2021'], $media->teamPhoto(101, 2026));
    }

    private function touch(string $path): void
    {
        touch($this->root . '/img/' . $path);
    }
}
