<?php

namespace App\Pdf\Document;

use App\Pdf\PdfCatalog;
use App\Pdf\Ranking\RankingDetailDocument;
use App\Pdf\Ranking\RankingSource;
use App\Pdf\Ranking\RankingTable;

/**
 * Ranking by phase of a cup (`ranking-phases`, ex-…CltNiveauPhase): pools first, then the phases by level; a pool
 * (type C) shows its ranking, an elimination phase (type E) its games; for a cup, without the date and place of each phase.
 */
final class RankingPhasesDocument extends RankingDetailDocument
{
    private const ELIMINATION = 'E';

    public function document(): string
    {
        return PdfCatalog::RANKING_PHASES;
    }

    protected function titleLabel(): string
    {
        return 'ranking.phases_title';
    }

    protected function sections(string $season, string $code, string $type, array $rules, RankingSource $source, array $labels): array
    {
        $rankings = [];
        foreach ($this->rankings->gamedayRankings($season, $code, $source) as $team) {
            // A team that did not play the phase is not listed (legacy rule).
            if ((int) $team['J'] > 0) {
                $rankings[(int) $team['Id_journee']][] = RankingTable::row($team, (int) $team['Clt']);
            }
        }
        $games = [];
        foreach ($this->rankings->games($season, $code, $source) as $game) {
            $games[(int) $game['Id_journee']][] = RankingTable::gameRow($game);
        }

        $sections = [];
        foreach ($this->rankings->gamedays($season, $code, $source) as $gameday) {
            $id = (int) $gameday['Id'];
            // A cup is read phase by phase: the date and place of each phase add nothing (2026-11-01).
            $section = [
                'title' => (string) $gameday['Phase'],
                'subtitle' => $type === RankingTable::CUP ? null : RankingTable::gamedayLabel($gameday['Date_debut'], $gameday['Lieu']),
            ];
            if ($gameday['Type'] === self::ELIMINATION && isset($games[$id])) {
                $sections[] = [...$section, 'columns' => RankingTable::gameColumns($labels), 'rows' => $games[$id]];
            } elseif ($gameday['Type'] !== self::ELIMINATION && isset($rankings[$id])) {
                $sections[] = [...$section, 'rows' => $rankings[$id]];
            }
        }

        return $sections;
    }
}
