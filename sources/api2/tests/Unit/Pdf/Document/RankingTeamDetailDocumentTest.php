<?php

namespace App\Tests\Unit\Pdf\Document;

use App\Pdf\Document\RankingTeamDetailDocument;
use PHPUnit\Framework\TestCase;

/** Separators of the games of each team (`ranking-team-detail`, PDF_GOTENBERG.md § 7.4). */
final class RankingTeamDetailDocumentTest extends TestCase
{
    private const GAME = ['Date_debut' => '2026-04-01', 'Lieu' => 'Rivecity', 'Phase' => 'Poule A'];

    public function testACupGroupsTheGamesByPhaseOtherCompetitionsByDateAndPlace(): void
    {
        self::assertSame('Poule A', RankingTeamDetailDocument::gamedayGroup(self::GAME, 'CP'));
        self::assertSame('01/04/2026 - Rivecity', RankingTeamDetailDocument::gamedayGroup([...self::GAME, 'Phase' => ''], 'CP'), 'phase not filled in');
        self::assertSame('01/04/2026 - Rivecity', RankingTeamDetailDocument::gamedayGroup(self::GAME, 'CHPT'));
    }
}
