<?php

namespace App\Pdf\Document;

use App\Pdf\PdfCatalog;
use App\Pdf\Ranking\RankingDetailDocument;
use App\Pdf\Ranking\RankingSource;
use App\Pdf\Ranking\RankingTable;

/** Ranking of each gameday (`ranking-gameday-detail`, ex-…CltNiveauJournee): one table per gameday, by date. */
final class RankingGamedayDetailDocument extends RankingDetailDocument
{
    public function document(): string
    {
        return PdfCatalog::RANKING_GAMEDAY_DETAIL;
    }

    protected function titleLabel(): string
    {
        return 'ranking.gameday_detail_title';
    }

    protected function sections(string $season, string $code, string $type, array $rules, RankingSource $source, array $labels): array
    {
        $gamedays = [];
        foreach ($this->rankings->gamedayRankings($season, $code, $source) as $team) {
            $gamedays[(int) $team['Id_journee']] ??= [
                'date' => (string) $team['Date_debut'],
                'title' => RankingTable::gamedayLabel($team['Date_debut'], $team['Lieu']),
                'subtitle' => (string) $team['Phase'],
                'rows' => [],
            ];
            $gamedays[(int) $team['Id_journee']]['rows'][] = RankingTable::row($team, (int) $team['Clt']);
        }
        // By date, as the legacy sheet (the repository orders by phase for the phases document).
        uasort($gamedays, static fn (array $a, array $b): int => strcmp($a['date'], $b['date']));

        return array_values(array_map(static fn (array $gameday): array => [
            'title' => $gameday['title'],
            'subtitle' => $gameday['subtitle'],
            'rows' => $gameday['rows'],
        ], $gamedays));
    }
}
