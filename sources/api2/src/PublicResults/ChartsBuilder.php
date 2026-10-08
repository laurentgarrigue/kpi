<?php

namespace App\PublicResults;

/**
 * Construit l'arbre des tableaux « compétition → tours → phases → équipes / matchs » à partir des lignes du
 * repository. Les clés et leur ORDRE d'insertion font partie du contrat JSON d'app2 (tests de caractérisation) :
 * modifier l'ordre des affectations ci-dessous change la réponse.
 *
 * Format Event : libellés d'attente résolus, `d_id` par phase, équipes des poules de CP déduites des matchs
 * quand la poule n'a pas encore de classement. Format Group : codes de matchs seuls pour les poules de CP,
 * `order` / `tour` par compétition et tri par ordre puis tour.
 */
final class ChartsBuilder
{
    public function __construct(private readonly MatchLabelParser $labelParser)
    {
    }

    /**
     * @param list<array<string, mixed>> $gameRows   lignes de PublicResultsRepository::findChartGames()
     * @param list<array<string, mixed>> $teamRows   lignes de PublicResultsRepository::findChartTeams()
     * @param callable(string $season, string $code, string $type): list<array<string, mixed>> $rankingLoader
     *
     * @return list<array<string, mixed>>
     */
    public function build(array $gameRows, array $teamRows, ResultsFormat $format, callable $rankingLoader): array
    {
        $games = $this->gamesByGameday($gameRows, $format);
        $charts = [];
        $rankedCompetitions = [];

        foreach ($teamRows as $row) {
            $code = $row['c_code'];
            $phaseOrder = (100 - $row['d_level']) . '-' . $row['d_phase'];
            $charts[$code]['type'] = $row['c_type'];
            $charts[$code]['code'] = $code;
            $charts[$code]['libelle'] = $row['c_category'];
            $charts[$code]['status'] = $row['c_status'];
            $charts[$code]['season'] = $row['c_season'];
            if ($format === ResultsFormat::Group) {
                $charts[$code]['order'] = (int) $row['c_order'];
                $charts[$code]['tour'] = (int) $row['c_tour'];
            }

            if (($row['c_type'] === 'CHPT' && $row['c_status'] !== 'ATT') || ($row['c_type'] === 'CP')) {
                $rankedCompetitions[$code] = ['code' => $code, 'season' => $row['c_season'], 'type' => $row['c_type']];
            }

            $round = $row['d_round'];
            $charts[$code]['rounds'][$round]['type'] = $row['d_type'];
            if ($format === ResultsFormat::Group || $row['t_id'] !== null) {
                $charts[$code]['rounds'][$round]['phases'][$phaseOrder]['teams'][] = $row;
            }
            $phase = &$charts[$code]['rounds'][$round]['phases'][$phaseOrder];
            $phase['type'] = $row['d_type'];
            $phase['libelle'] = $row['d_phase'];
            $phase['level'] = $row['d_level'];
            $phase['t_count'] = $row['t_count'];
            if ($format === ResultsFormat::Event) {
                $phase['d_id'] = $row['d_id'];
            }
            $phase['games'] = $games[$row['d_id']] ?? null;
            unset($phase);
        }

        if ($format === ResultsFormat::Event) {
            $this->deriveCupPoolTeams($charts, $games);
        }

        foreach ($rankedCompetitions as $competition) {
            $charts[$competition['code']]['ranking'] = $rankingLoader(
                $competition['season'],
                $competition['code'],
                $competition['type'],
            );
        }

        if ($format === ResultsFormat::Group) {
            // Ordre puis tour croissants (page Équipe d'app2) ; la page Tableaux d'app2 retrie si besoin.
            uasort($charts, static function (array $a, array $b): int {
                return [$a['order'] ?? 0, $a['tour'] ?? 0] <=> [$b['order'] ?? 0, $b['tour'] ?? 0];
            });
        }

        return array_values($charts);
    }

    /**
     * Matchs rangés par journée : lignes complètes pour les éliminations et les championnats ; pour les poules
     * de coupe, la ligne complète (format Event) ou le seul code du match (format Group).
     *
     * @param list<array<string, mixed>> $gameRows
     *
     * @return array<int|string, list<mixed>>
     */
    private function gamesByGameday(array $gameRows, ResultsFormat $format): array
    {
        $games = [];
        foreach ($gameRows as $row) {
            if ($row['d_type'] === 'E' || $row['c_type'] === 'CHPT') {
                if ($format === ResultsFormat::Event) {
                    $row = $this->withPlaceholderLabels($row);
                }
                $games[$row['d_id']][] = $row;
            } elseif ($row['d_type'] === 'C' && $row['c_type'] === 'CP') {
                $games[$row['d_id']][] = $format === ResultsFormat::Event ? $row : $row['g_code'];
            }
        }

        return $games;
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function withPlaceholderLabels(array $row): array
    {
        if (!$row['t_a_label'] && $row['g_code']) {
            $parsed = $this->labelParser->parse($row['g_code']);
            if (isset($parsed[0])) {
                $row['t_a_label'] = $parsed[0];
            }
            if (isset($parsed[1])) {
                $row['t_b_label'] = $parsed[1];
            }
        }

        return $row;
    }

    /**
     * Poules de coupe sans classement : équipes déduites de leurs matchs (format Event).
     *
     * @param array<string, array<string, mixed>> $charts
     * @param array<int|string, list<mixed>>      $games
     */
    private function deriveCupPoolTeams(array &$charts, array $games): void
    {
        foreach ($charts as &$chart) {
            if ($chart['type'] !== 'CP') {
                continue;
            }
            foreach ($chart['rounds'] as &$round) {
                foreach ($round['phases'] as &$phase) {
                    if ($phase['type'] !== 'C' || !empty($phase['teams'])) {
                        continue;
                    }
                    $gamedayId = $phase['d_id'] ?? null;
                    if (!$gamedayId || empty($games[$gamedayId])) {
                        continue;
                    }
                    $phase['teams'] = $this->teamsFromGames($games[$gamedayId]);
                    if ($phase['teams'] === []) {
                        unset($phase['teams']);
                    }
                }
            }
        }
        unset($chart, $round, $phase);
    }

    /**
     * @param list<mixed> $games
     *
     * @return list<array<string, mixed>>
     */
    private function teamsFromGames(array $games): array
    {
        $teams = [];
        $seen = [];
        foreach ($games as $game) {
            if (!is_array($game)) {
                continue;
            }
            $labelA = $game['t_a_label'] ?? null;
            $labelB = $game['t_b_label'] ?? null;
            if ((!$labelA || !$labelB) && !empty($game['g_code'])) {
                $parsed = $this->labelParser->parse($game['g_code']);
                if (!$labelA && isset($parsed[0])) {
                    $labelA = $parsed[0];
                }
                if (!$labelB && isset($parsed[1])) {
                    $labelB = $parsed[1];
                }
            }
            foreach (['a' => $labelA, 'b' => $labelB] as $side => $label) {
                $id = (int) ($game["t_{$side}_id"] ?? 0);
                // Équipes réelles dédoublonnées par id, équipes d'attente par libellé.
                $key = $id > 1 ? "id:$id" : "lbl:$label";
                if (!$label || isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $teams[] = [
                    't_id' => $id > 1 ? $id : null,
                    't_number' => $game["t_{$side}_number"] ?? null,
                    't_label' => $label, 't_club' => $game["t_{$side}_club"] ?? null,
                    't_clt' => null, 't_pts' => null, 't_pld' => null,
                    't_diff' => null, 't_logo' => $game["t_{$side}_logo"] ?? null,
                ];
            }
        }

        $hasNumbers = array_filter($teams, static fn (array $team): bool => $team['t_number'] !== null);
        if ($hasNumbers) {
            usort($teams, static fn (array $a, array $b): int => ((int) ($a['t_number'] ?? 0)) <=> ((int) ($b['t_number'] ?? 0)));
        } else {
            usort($teams, static fn (array $a, array $b): int => strnatcasecmp($a['t_label'], $b['t_label']));
        }

        return $teams;
    }
}
