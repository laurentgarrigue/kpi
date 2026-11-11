<?php

namespace App\MatchMedals;

use App\MatchMedals\Model\Award;
use App\MatchMedals\Model\AwardScope;
use App\MatchMedals\Model\GameEvent;
use App\MatchMedals\Model\GameRecord;
use App\MatchMedals\Model\Player;
use App\MatchMedals\Model\Referee;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Database side of the match medals (FEATURE_MATCH_MEDALS.md § 6.2): loads a game from the legacy tables, stores
 * its result in kp_match_insight / kp_match_medal / kp_match_card / kp_match_referee, keeps the season thresholds.
 */
final class MatchMedalRepository
{
    /** Legacy sheets store « 00: » + the typed time: an empty time becomes 00:00:00 and means « not recorded ». */
    private const UNRECORDED_TIME = '00:00:00';
    private const COACH = 'E';

    /**
     * Everything a game's medals depend on, hashed by MariaDB: score, final period, type, publication, designated
     * referees, facts and coaches. Same expression for the stored hash and for the detection of changes (§ 6.3).
     */
    private const SOURCE_HASH_SQL = "MD5(CONCAT_WS('|', m.ScoreA, m.ScoreB, m.Periode, m.Type, m.Publication, j.Publication,
        m.Id_equipeA, m.Id_equipeB, m.Matric_arbitre_principal, m.Matric_arbitre_secondaire,
        (SELECT GROUP_CONCAT(CONCAT_WS(',', d.Id, d.Periode, d.Temps, d.Id_evt_match, d.Competiteur, d.Equipe_A_B, d.motif) ORDER BY d.Id SEPARATOR ';')
            FROM kp_match_detail d WHERE d.Id_match = m.Id),
        (SELECT GROUP_CONCAT(mj.Matric ORDER BY mj.Matric) FROM kp_match_joueur mj WHERE mj.Id_match = m.Id AND mj.Capitaine = 'E')))";

    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array{game: GameRecord, season: string, teams: array{A: int|null, B: int|null}, hash: string}|null */
    public function loadGame(int $matchId): ?array
    {
        $row = $this->connection->fetchAssociative(
            'SELECT m.Id, m.Type, m.Periode, m.ScoreA, m.ScoreB, m.Validation, m.Publication match_publication,
                j.Publication gameday_publication, j.Code_saison season, m.Id_equipeA, m.Id_equipeB,
                m.Matric_arbitre_principal, m.Matric_arbitre_secondaire, '.self::SOURCE_HASH_SQL.' hash
            FROM kp_match m INNER JOIN kp_journee j ON (j.Id = m.Id_journee) WHERE m.Id = ?',
            [$matchId],
        );
        if ($row === false) {
            return null;
        }
        $coaches = array_map('intval', $this->connection->fetchFirstColumn(
            'SELECT Matric FROM kp_match_joueur WHERE Id_match = ? AND Capitaine = ?',
            [$matchId, self::COACH],
        ));
        $events = array_map(
            static fn (array $e): GameEvent => new GameEvent(
                (string) $e['Id'],
                (string) $e['Equipe_A_B'],
                (string) $e['Id_evt_match'],
                $e['Periode'] === null ? null : trim((string) $e['Periode']),
                self::remainingSeconds($e['Temps']),
                $e['Competiteur'] === null ? null : new Player((int) $e['Competiteur'], in_array((int) $e['Competiteur'], $coaches, true)),
                $e['motif'] === null || $e['motif'] === '' ? null : (string) $e['motif'],
            ),
            $this->connection->fetchAllAssociative(
                "SELECT Id, Equipe_A_B, Id_evt_match, Periode, Temps, Competiteur, motif FROM kp_match_detail
                WHERE Id_match = ? AND Equipe_A_B IN ('A', 'B') AND Id_evt_match IN ('B', 'V', 'J', 'R', 'D')
                ORDER BY date_insert, Id",
                [$matchId],
            ),
        );
        $referees = [];
        foreach ([[Referee::PRINCIPAL, $row['Matric_arbitre_principal']], [Referee::SECONDARY, $row['Matric_arbitre_secondaire']]] as [$role, $matric]) {
            if ((int) $matric > 0) {
                $referees[] = new Referee((int) $matric, $role);
            }
        }

        return [
            'game' => new GameRecord(
                (int) $row['Id'],
                (string) $row['Type'],
                $row['Periode'] === null ? null : (string) $row['Periode'],
                $row['ScoreA'] === null ? null : trim((string) $row['ScoreA']),
                $row['ScoreB'] === null ? null : trim((string) $row['ScoreB']),
                $row['Validation'] === 'O',
                $row['match_publication'] === 'O' && $row['gameday_publication'] === 'O',
                $row['Id_equipeA'] !== null && $row['Id_equipeB'] !== null,
                $events,
                $referees,
            ),
            'season' => (string) $row['season'],
            'teams' => ['A' => $row['Id_equipeA'] === null ? null : (int) $row['Id_equipeA'], 'B' => $row['Id_equipeB'] === null ? null : (int) $row['Id_equipeB']],
            'hash' => (string) $row['hash'],
        ];
    }

    /** Remaining time in seconds from a TIME « HH:MM:SS » holding « 00:mm:ss »; null when not recorded. */
    public static function remainingSeconds(mixed $time): ?int
    {
        if ($time === null || $time === '' || $time === self::UNRECORDED_TIME) {
            return null;
        }
        $parts = explode(':', (string) $time);
        if (count($parts) < 2) {
            return null;
        }
        [$minutes, $seconds] = count($parts) === 3 ? [$parts[1], $parts[2]] : $parts;

        return (int) $minutes * 60 + (int) $seconds;
    }

    /** @return list<int> validated games of a season */
    public function validatedGameIds(string $season): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn(
            "SELECT m.Id FROM kp_match m INNER JOIN kp_journee j ON (j.Id = m.Id_journee)
            WHERE m.Validation = 'O' AND j.Code_saison = ? ORDER BY m.Id",
            [$season],
        ));
    }

    /**
     * @param list<string> $excludedSeasons seasons whose calculations were deleted: never brought back here
     *
     * @return list<int> validated games never computed, or whose sources changed since (§ 6.3)
     */
    public function staleGameIds(array $excludedSeasons = []): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn(
            'SELECT m.Id FROM kp_match m INNER JOIN kp_journee j ON (j.Id = m.Id_journee)
            LEFT JOIN kp_match_insight i ON (i.match_id = m.Id)
            WHERE m.Validation = \'O\' AND j.Code_saison NOT IN (?) AND (i.match_id IS NULL OR i.source_hash <> '.self::SOURCE_HASH_SQL.')
            ORDER BY m.Id',
            [$excludedSeasons === [] ? [''] : $excludedSeasons],
            [ArrayParameterType::STRING],
        ));
    }

    /** @return int number of games whose calculations were removed, with the row of the season */
    public function deleteSeason(string $season): int
    {
        $games = array_map('intval', $this->connection->fetchFirstColumn('SELECT match_id FROM kp_match_insight WHERE season = ?', [$season]));
        $this->connection->transactional(function () use ($games, $season): void {
            foreach ($games as $gameId) {
                $this->deleteRows($gameId);
            }
            $this->connection->executeStatement('DELETE FROM kp_medal_season WHERE season = ?', [$season]);
        });

        return count($games);
    }

    public function seasonExists(string $season): bool
    {
        return $this->connection->fetchOne('SELECT 1 FROM kp_medal_season WHERE season = ?', [$season]) !== false
            || $this->connection->fetchOne('SELECT 1 FROM kp_match_insight WHERE season = ? LIMIT 1', [$season]) !== false;
    }

    /** @return list<int> computed games that are no longer validated (or no longer exist), optionally of one season */
    public function orphanGameIds(?string $season = null): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn(
            "SELECT i.match_id FROM kp_match_insight i LEFT JOIN kp_match m ON (m.Id = i.match_id)
            WHERE (m.Id IS NULL OR m.Validation <> 'O') AND (? IS NULL OR i.season = ?)",
            [$season, $season],
        ));
    }

    /** @param array{A: int|null, B: int|null} $teams */
    public function save(MatchMedals $result, string $season, array $teams, string $hash): void
    {
        $timeline = $result->timeline;
        $gameId = $timeline->game->id;
        $this->connection->transactional(function () use ($result, $timeline, $gameId, $season, $teams, $hash): void {
            $this->deleteRows($gameId);
            $goals = $timeline->goals();
            $first = $timeline->firstGoal();
            $count = static fn (string $side, callable $filter): int => count(array_filter($timeline->exclusions($side), $filter));
            $this->connection->insert('kp_match_insight', [
                'match_id' => $gameId,
                'season' => $season,
                'is_eligible' => (int) $timeline->isEligible(),
                'timeline_reliable' => (int) $timeline->isReliable(),
                'labels' => json_encode($result->labels(), JSON_THROW_ON_ERROR),
                'winner_side' => $timeline->isEligible() ? $timeline->winner() : null,
                'overtimes' => $timeline->overtimes(),
                'lead_changes' => $timeline->leadChanges(),
                'equalizers' => $timeline->equalizers(),
                'first_goal_side' => $first?->side(),
                'first_goal_seconds' => $first?->seconds,
                'max_deficit_a' => $timeline->maxDeficit('A'),
                'max_deficit_b' => $timeline->maxDeficit('B'),
                'exclusions_a' => count($timeline->exclusions('A')),
                'exclusions_b' => count($timeline->exclusions('B')),
                'powerplay_goals_a' => count(array_filter($goals, static fn ($g): bool => $g->isPowerplay && $g->side() === 'A')),
                'powerplay_goals_b' => count(array_filter($goals, static fn ($g): bool => $g->isPowerplay && $g->side() === 'B')),
                'penalty_killed_a' => $count('A', static fn ($x): bool => $x->isKilled),
                'penalty_killed_b' => $count('B', static fn ($x): bool => $x->isKilled),
                'goal_seconds' => json_encode(array_map(static fn ($g): array => ['side' => $g->side(), 'seconds' => $g->seconds], $goals), JSON_THROW_ON_ERROR),
                'source_hash' => $hash,
                'computed_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);
            foreach ($result->awards as $award) {
                if ($award->scope !== AwardScope::Game) {
                    $this->insertAward($gameId, $award, $teams);
                }
            }
            if ($timeline->isEligible()) {
                foreach ($timeline->cards() as ['event' => $card, 'seconds' => $seconds]) {
                    $this->connection->insert('kp_match_card', [
                        'match_id' => $gameId, 'event_detail_id' => $card->id, 'team_side' => $card->side, 'code' => $card->code,
                        'motif' => $card->motif, 'period' => $card->period, 'game_seconds' => $seconds,
                    ]);
                }
                foreach ($timeline->referees() as $referee) {
                    $this->connection->insert('kp_match_referee', ['match_id' => $gameId, 'referee_matric' => $referee->matric, 'role' => $referee->role]);
                }
            }
        });
    }

    public function delete(int $gameId): void
    {
        $this->connection->transactional(fn () => $this->deleteRows($gameId));
    }

    /** @return array<string, mixed>|null copy of the thresholds a season was computed with */
    public function seasonThresholds(string $season): ?array
    {
        $json = $this->connection->fetchOne('SELECT thresholds FROM kp_medal_season WHERE season = ?', [$season]);

        return $json === false ? null : json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $thresholds */
    public function saveSeason(string $season, array $thresholds, int $rulesVersion): void
    {
        $this->connection->executeStatement(
            'INSERT INTO kp_medal_season (season, thresholds, rules_version, computed_at) VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE thresholds = VALUES(thresholds), rules_version = VALUES(rules_version), computed_at = VALUES(computed_at)',
            [$season, json_encode($thresholds, JSON_THROW_ON_ERROR), $rulesVersion],
        );
    }

    /** Counts shown in the admin (§ 9.3). */
    public function refreshSeasonCounts(string $season): void
    {
        $this->connection->executeStatement(
            'UPDATE kp_medal_season s SET
                games_count = (SELECT COUNT(*) FROM kp_match_insight i WHERE i.season = s.season AND i.is_eligible = 1),
                reliable_games_count = (SELECT COUNT(*) FROM kp_match_insight i WHERE i.season = s.season AND i.timeline_reliable = 1)
            WHERE s.season = ?',
            [$season],
        );
    }

    /** @return list<string> */
    public function retiredCodes(): array
    {
        return $this->connection->fetchFirstColumn("SELECT code FROM kp_medal_catalog WHERE status = 'retired'");
    }

    /** @param array{A: int|null, B: int|null} $teams */
    private function insertAward(int $gameId, Award $award, array $teams): void
    {
        $this->connection->insert('kp_match_medal', [
            'match_id' => $gameId,
            'code' => $award->code,
            'scope' => $award->scope->value,
            'team_side' => $award->side,
            'team_id' => $award->side === null ? null : $teams[$award->side],
            'person_matric' => $award->matric,
            'event_detail_id' => $award->eventId,
            'game_seconds' => $award->gameSeconds,
        ]);
    }

    private function deleteRows(int $gameId): void
    {
        foreach (['kp_match_medal', 'kp_match_card', 'kp_match_referee', 'kp_match_insight'] as $table) {
            $this->connection->executeStatement("DELETE FROM $table WHERE match_id = ?", [$gameId]);
        }
    }
}
