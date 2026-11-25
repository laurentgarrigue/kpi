<?php

namespace App\ClubRanking;

use App\ClubRanking\Model\Adjustment;
use App\ClubRanking\Model\Category;
use App\ClubRanking\Model\Line;
use App\ClubRanking\Model\Rule;
use App\ClubRanking\Model\RuleType;
use App\ClubRanking\Model\Scale;
use Doctrine\DBAL\Connection;

/** Tables kp_club_ranking_* (PAGE_CLASSEMENT_NATIONAL_CLUBS.md § 9): seasons, scales, lines, entries, publications. */
final class ClubRankingRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function season(string $season): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM kp_club_ranking_season WHERE season = ?', [$season]);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function seasons(): array
    {
        return $this->connection->fetchAllAssociative('SELECT season, status, computed_at, validated_at, stale FROM kp_club_ranking_season ORDER BY season DESC');
    }

    public function createSeason(string $season): void
    {
        $this->connection->executeStatement("INSERT IGNORE INTO kp_club_ranking_season (season, status) VALUES (?, 'draft')", [$season]);
    }

    /** @param array<string, mixed> $fields */
    public function updateSeason(string $season, array $fields): void
    {
        $this->connection->update('kp_club_ranking_season', $fields, ['season' => $season]);
    }

    public function markStale(string $season): void
    {
        $this->connection->executeStatement('UPDATE kp_club_ranking_season SET stale = 1 WHERE season = ? AND computed_at IS NOT NULL', [$season]);
    }

    public function scale(string $season): Scale
    {
        $row = $this->season($season) ?? [];

        return new Scale(
            $season,
            array_map(self::rule(...), $this->ruleRows($season)),
            array_map(self::category(...), $this->categoryRows($season)),
            self::csv((string) ($row['national_prefixes'] ?? implode(',', Scale::DEFAULT_NATIONAL_PREFIXES))),
            self::csv((string) ($row['excluded_competitions'] ?? '')),
        );
    }

    /** @return list<array<string, mixed>> */
    public function ruleRows(string $season): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM kp_club_ranking_rule WHERE season = ? ORDER BY article, position', [$season]);
    }

    /** @return list<array<string, mixed>> */
    public function categoryRows(string $season): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM kp_club_ranking_category WHERE season = ? ORDER BY code', [$season]);
    }

    /** @param array<string, mixed> $params */
    public function saveRule(string $season, ?int $id, string $article, int $rank, string $label, string $shortLabel, int $points, RuleType $type, array $params, string $note): int
    {
        $fields = [
            'season' => $season, 'article' => $article, 'position' => $rank, 'label' => $label, 'short_label' => $shortLabel,
            'points' => $points, 'calc_type' => $type->value, 'params' => json_encode((object) $params, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), 'note' => $note,
        ];
        if ($id === null) {
            $this->connection->insert('kp_club_ranking_rule', $fields);

            return (int) $this->connection->lastInsertId();
        }
        $this->connection->update('kp_club_ranking_rule', $fields, ['id' => $id, 'season' => $season]);

        return $id;
    }

    public function deleteRule(string $season, int $id): int
    {
        return $this->connection->delete('kp_club_ranking_rule', ['id' => $id, 'season' => $season]);
    }

    /** @param list<string> $entryCodes */
    public function saveCategory(string $season, ?int $id, string $code, string $label, array $entryCodes, ?string $finalCode, ?string $regularCode): int
    {
        $fields = ['season' => $season, 'code' => $code, 'label' => $label, 'entry_codes' => implode(',', $entryCodes), 'final_code' => $finalCode, 'regular_code' => $regularCode];
        if ($id === null) {
            $this->connection->insert('kp_club_ranking_category', $fields);

            return (int) $this->connection->lastInsertId();
        }
        $this->connection->update('kp_club_ranking_category', $fields, ['id' => $id, 'season' => $season]);

        return $id;
    }

    public function deleteCategory(string $season, int $id): int
    {
        return $this->connection->delete('kp_club_ranking_category', ['id' => $id, 'season' => $season]);
    }

    /** CNC-01: the scale of a season becomes the draft scale of another one; the source is left untouched. */
    public function copyScale(string $from, string $to): void
    {
        $this->connection->transactional(function (Connection $connection) use ($from, $to): void {
            $connection->executeStatement("INSERT IGNORE INTO kp_club_ranking_season (season, status, top_n, national_prefixes, excluded_competitions)
                SELECT ?, 'draft', top_n, national_prefixes, excluded_competitions FROM kp_club_ranking_season WHERE season = ?", [$to, $from]);
            $connection->executeStatement('INSERT INTO kp_club_ranking_rule (season, article, position, label, short_label, points, calc_type, params, note)
                SELECT ?, article, position, label, short_label, points, calc_type, params, note FROM kp_club_ranking_rule WHERE season = ?', [$to, $from]);
            $connection->executeStatement('INSERT INTO kp_club_ranking_category (season, code, label, entry_codes, final_code, regular_code)
                SELECT ?, code, label, entry_codes, final_code, regular_code FROM kp_club_ranking_category WHERE season = ?', [$to, $from]);
        });
    }

    public function hasScale(string $season): bool
    {
        return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM kp_club_ranking_rule WHERE season = ?', [$season]) > 0;
    }

    /** @param list<Line> $lines */
    public function replaceLines(string $season, array $lines): void
    {
        $this->connection->transactional(function (Connection $connection) use ($season, $lines): void {
            $connection->executeStatement('DELETE FROM kp_club_ranking_line WHERE season = ?', [$season]);
            foreach ($lines as $line) {
                $connection->insert('kp_club_ranking_line', [
                    'season' => $season, 'rule_code' => $line->ruleCode, 'club_code' => $line->clubCode, 'points' => $line->points,
                    'reason' => mb_substr($line->reason, 0, 500), 'source_type' => $line->sourceType, 'source_id' => $line->sourceId, 'matric' => $line->matric,
                ]);
            }
        });
    }

    /** @return list<Line> */
    public function lines(string $season): array
    {
        return array_map(
            static fn (array $row): Line => new Line((string) $row['rule_code'], (string) $row['club_code'], (int) $row['points'], (string) $row['reason'], (string) $row['source_type'], (string) $row['source_id'], $row['matric'] === null ? null : (int) $row['matric']),
            $this->connection->fetchAllAssociative('SELECT * FROM kp_club_ranking_line WHERE season = ? ORDER BY rule_code, club_code, id', [$season]),
        );
    }

    /** @return list<array<string, mixed>> */
    public function entries(string $season): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT e.*, l.Nom, l.Prenom, l.Numero_club, c.Libelle AS club_label
             FROM kp_club_ranking_entry e
             LEFT JOIN kp_licence l ON l.Matric = e.matric
             LEFT JOIN kp_club c ON c.Code = COALESCE(NULLIF(e.club_code, \'\'), l.Numero_club)
             WHERE e.season = ? ORDER BY e.rule_code, e.id',
            [$season],
        );
    }

    public function addEntry(string $season, string $ruleCode, ?int $matric, ?string $clubCode, string $detail, ?string $author): int
    {
        $this->connection->insert('kp_club_ranking_entry', [
            'season' => $season, 'rule_code' => $ruleCode, 'matric' => $matric, 'club_code' => $clubCode, 'detail' => $detail,
            'created_by' => $author, 'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function deleteEntry(string $season, int $id): int
    {
        return $this->connection->delete('kp_club_ranking_entry', ['id' => $id, 'season' => $season]);
    }

    /** @return list<Adjustment> */
    public function adjustments(string $season): array
    {
        return array_map(
            static fn (array $row): Adjustment => new Adjustment((string) $row['rule_code'], (string) $row['club_code'], (int) $row['points'], (string) $row['reason'], (int) $row['id'], $row['created_by'], (string) $row['created_at']),
            $this->connection->fetchAllAssociative('SELECT * FROM kp_club_ranking_adjustment WHERE season = ? ORDER BY id', [$season]),
        );
    }

    public function addAdjustment(string $season, Adjustment $adjustment): int
    {
        $this->connection->insert('kp_club_ranking_adjustment', [
            'season' => $season, 'rule_code' => $adjustment->ruleCode, 'club_code' => $adjustment->clubCode, 'points' => $adjustment->points,
            'reason' => $adjustment->reason, 'created_by' => $adjustment->author, 'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->connection->lastInsertId();
    }

    public function deleteAdjustment(string $season, int $id): int
    {
        return $this->connection->delete('kp_club_ranking_adjustment', ['id' => $id, 'season' => $season]);
    }

    /** @return array<int, list<string>> team number → partner clubs */
    public function ententes(string $season): array
    {
        $ententes = [];
        foreach ($this->connection->fetchAllAssociative('SELECT team_number, club_code FROM kp_club_ranking_entente WHERE season = ? ORDER BY team_number, club_code', [$season]) as $row) {
            $ententes[(int) $row['team_number']][] = (string) $row['club_code'];
        }

        return $ententes;
    }

    public function addEntente(string $season, int $teamNumber, string $clubCode): void
    {
        $this->connection->executeStatement('INSERT IGNORE INTO kp_club_ranking_entente (season, team_number, club_code) VALUES (?, ?, ?)', [$season, $teamNumber, $clubCode]);
    }

    public function deleteEntente(string $season, int $teamNumber, string $clubCode): int
    {
        return $this->connection->delete('kp_club_ranking_entente', ['season' => $season, 'team_number' => $teamNumber, 'club_code' => $clubCode]);
    }

    /** @return array<string, int> normalised name → matric */
    public function aliases(): array
    {
        $aliases = [];
        foreach ($this->connection->fetchAllAssociative('SELECT name, matric FROM kp_club_ranking_alias') as $row) {
            $aliases[(string) $row['name']] = (int) $row['matric'];
        }

        return $aliases;
    }

    public function saveAlias(string $name, int $matric): void
    {
        $this->connection->executeStatement('REPLACE INTO kp_club_ranking_alias (name, matric) VALUES (?, ?)', [NameNormalizer::normalize($name), $matric]);
    }

    public function deleteAlias(string $name): int
    {
        return $this->connection->delete('kp_club_ranking_alias', ['name' => NameNormalizer::normalize($name)]);
    }

    /** @param array<string, mixed> $payload */
    public function addPublication(string $season, array $payload, ?string $author): int
    {
        $version = (int) $this->connection->fetchOne('SELECT COALESCE(MAX(version), 0) + 1 FROM kp_club_ranking_publication WHERE season = ?', [$season]);
        $this->connection->insert('kp_club_ranking_publication', [
            'season' => $season, 'version' => $version, 'published_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'published_by' => $author, 'payload' => json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
        ]);

        return $version;
    }

    /** @return list<array<string, mixed>> */
    public function publications(string $season): array
    {
        return $this->connection->fetchAllAssociative('SELECT version, published_at, published_by FROM kp_club_ranking_publication WHERE season = ? ORDER BY version DESC', [$season]);
    }

    /** @return array<string, mixed>|null the payload of a publication, the latest one by default */
    public function publication(string $season, ?int $version = null): ?array
    {
        $payload = $version === null
            ? $this->connection->fetchOne('SELECT payload FROM kp_club_ranking_publication WHERE season = ? ORDER BY version DESC LIMIT 1', [$season])
            : $this->connection->fetchOne('SELECT payload FROM kp_club_ranking_publication WHERE season = ? AND version = ?', [$season, $version]);

        return is_string($payload) ? json_decode($payload, true, 512, JSON_THROW_ON_ERROR) : null;
    }

    /** @param array<string, mixed> $row */
    public static function rule(array $row): Rule
    {
        $params = json_decode((string) $row['params'], true);

        return new Rule((string) $row['article'], (int) $row['position'], (string) $row['label'], (string) $row['short_label'], (int) $row['points'],
            RuleType::from((string) $row['calc_type']), is_array($params) ? $params : [], (string) $row['note'], (int) $row['id']);
    }

    /** @param array<string, mixed> $row */
    public static function category(array $row): Category
    {
        return new Category((string) $row['code'], (string) $row['label'], self::csv((string) $row['entry_codes']),
            self::nullable($row['final_code']), self::nullable($row['regular_code']), (int) $row['id']);
    }

    /** @return list<string> */
    public static function csv(string $value): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $value)), static fn (string $item): bool => $item !== ''));
    }

    private static function nullable(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
