<?php

namespace App\Pdf\Document;

use App\Pdf\PdfCatalog;
use App\Pdf\Ranking\RankingDetailDocument;
use App\Pdf\Ranking\RankingSource;
use App\Pdf\Ranking\RankingTable;

/**
 * Ranking with the games of each team (`ranking-team-detail`, ex-…CltChptDetail / …CltNiveauDetail): one section per
 * ranked team, its played games by gameday (by phase for a cup, without the points of the team).
 */
final class RankingTeamDetailDocument extends RankingDetailDocument
{
    public function document(): string
    {
        return PdfCatalog::RANKING_TEAM_DETAIL;
    }

    protected function titleLabel(): string
    {
        return 'ranking.team_detail_title';
    }

    protected function sections(string $season, string $code, string $type, array $rules, RankingSource $source, array $labels): array
    {
        $games = array_filter($this->rankings->games($season, $code, $source), RankingTable::isPlayed(...));
        $sections = [];
        foreach (RankingTable::rankingRows($this->rankings->teams($season, $code, $type, $source), $type, $rules['status'], $rules['final']) as $row) {
            $rows = [];
            $gameday = null;
            foreach ($games as $game) {
                if ((int) $game['Id_equipeA'] !== $row->team->id && (int) $game['Id_equipeB'] !== $row->team->id) {
                    continue;
                }
                if ($game['Id_journee'] !== $gameday) {
                    $gameday = $game['Id_journee'];
                    $rows[] = ['_group' => self::gamedayGroup($game, $type)];
                }
                $rows[] = RankingTable::gameRow($game);
            }
            $medal = $row->medal === null ? '' : ' · ' . $labels['ranking.medal_' . $row->medal];
            $sections[] = [
                'title' => sprintf('%d. %s%s', $row->rank, $row->team->label, $medal),
                'subtitle' => $type === RankingTable::CUP ? null : sprintf('%s %s · %s %d', $row->points, $labels['ranking.points'], $labels['ranking.played'], $row->played),
                'columns' => RankingTable::gameColumns($labels),
                'rows' => $rows,
                'empty' => $labels['ranking.no_game'],
            ];
        }

        return $sections;
    }

    /**
     * Separator of the games of a gameday: the phase for a cup (« Poule A », « Finale »), else its date and place.
     *
     * @param array<string, mixed> $game
     */
    public static function gamedayGroup(array $game, string $type): string
    {
        return $type === RankingTable::CUP && (string) $game['Phase'] !== ''
            ? (string) $game['Phase']
            : RankingTable::gamedayLabel($game['Date_debut'], $game['Lieu']);
    }
}
