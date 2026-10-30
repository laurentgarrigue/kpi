<?php

namespace App\Tests\Unit\Pdf\Document;

use App\Pdf\Document\GamesListDocument;
use PHPUnit\Framework\TestCase;

/** Columns and rows of the game list (PDF_GOTENBERG.md § 7.3). */
final class GamesListDocumentTest extends TestCase
{
    private const LABELS = [
        'player.number' => '#', 'games.date' => 'Date', 'games.time' => 'Time', 'games.competition' => 'Comp.',
        'games.phase' => 'Phase | Game', 'games.pitch' => 'Pitch', 'games.home' => 'Team A', 'games.score' => 'Score',
        'games.away' => 'Team B', 'games.referees' => 'Referees',
    ];

    public function testTheCompetitionColumnOnlyForSeveralCompetitionsAndOneColumnForTheReferees(): void
    {
        self::assertSame(
            ['number', 'date', 'time', 'phase', 'pitch', 'home', 'score', 'away', 'referees'],
            array_column(GamesListDocument::columns(self::LABELS, false), 'key'),
        );
        self::assertSame(
            ['number', 'date', 'time', 'competition', 'phase', 'pitch', 'home', 'score', 'away', 'referees'],
            array_column(GamesListDocument::columns(self::LABELS, true), 'key'),
        );
    }

    public function testRowShowsTheCompetitionLabelAndTheFirstRefereeAboveTheSecond(): void
    {
        $row = GamesListDocument::row(self::game(), false);

        self::assertSame('U18 Hommes', $row['competition'], 'the label, never the code');
        self::assertSame("Ref One\nRef Two", $row['referees']);

        $row = GamesListDocument::row([...self::game(), 'Soustitre2' => '', 'Arbitre_secondaire' => ''], false);
        self::assertSame('Championnat U18', $row['competition'], 'the title without a short label');
        self::assertSame('Ref One', $row['referees']);
    }

    /** @return array<string, mixed> */
    private static function game(): array
    {
        return [
            'Numero_ordre' => 3, 'Date_match' => '2026-05-01', 'Heure_match' => '10:30:00', 'Code_competition' => 'N1U18',
            'Soustitre2' => 'U18 Hommes', 'Competition' => 'Championnat U18', 'Phase' => 'Poule A', 'Libelle' => 'M3',
            'Terrain' => '1', 'LibelleA' => 'Alpha', 'LibelleB' => 'Bravo', 'ScoreA' => '2', 'ScoreB' => '1',
            'Validation' => 'O', 'Statut' => 'END', 'Arbitre_principal' => 'Ref One', 'Arbitre_secondaire' => 'Ref Two',
        ];
    }
}
