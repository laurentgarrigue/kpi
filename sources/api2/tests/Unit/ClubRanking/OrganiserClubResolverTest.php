<?php

namespace App\Tests\Unit\ClubRanking;

use App\ClubRanking\OrganiserClubResolver;
use PHPUnit\Framework\TestCase;

final class OrganiserClubResolverTest extends TestCase
{
    private OrganiserClubResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new OrganiserClubResolver(['6220' => 'KAYAK CLUB SAINT-OMER', '6221' => 'Club Évreux', '7000' => 'Club Doublon', '7001' => 'CLUB DOUBLON']);
    }

    public function testTheTypedCodeIsAuthoritative(): void
    {
        self::assertSame('6221', $this->resolver->code('6221', 'Kayak Club Saint-Omer'));
    }

    public function testWithoutCodeTheLabelIdentifiesTheClub(): void
    {
        self::assertSame('6220', $this->resolver->code(null, 'kayak club saint-omer'));
        self::assertSame('6221', $this->resolver->code('', 'CLUB EVREUX'));
    }

    public function testTwoClubsSharingALabelAreNotGuessed(): void
    {
        self::assertSame('', $this->resolver->code(null, 'Club Doublon'));
    }

    public function testUnknownLabelGivesNoCodeAndKeepsItsTypedLabel(): void
    {
        self::assertSame('', $this->resolver->code(null, 'Quelque part'));
        self::assertSame('Quelque part', $this->resolver->label(null, 'Quelque part'));
    }

    public function testTheLabelIsThatOfTheIdentifiedClub(): void
    {
        self::assertSame('Club Évreux', $this->resolver->label('6221', 'typo'));
        self::assertSame('KAYAK CLUB SAINT-OMER', $this->resolver->label(null, 'Kayak Club Saint-Omer'));
    }
}
