<?php

namespace App\MatchMedals;

use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Settings of the medals that the admin can change (FEATURE_MATCH_MEDALS.md § 5.4, § 6.3): the thresholds (the file
 * `match_medals.yaml` gives the original values, the admin stores its replacements) and the seasons whose
 * calculations were deleted. Nothing is kept in memory: a FrankenPHP worker serves many requests.
 */
final class MedalSettings
{
    private const THRESHOLDS = 'thresholds';
    private const DELETED_SEASONS = 'deleted_seasons';
    private const COMPUTE_JOB = 'compute_job';
    private const JOURNAL_ACTION = 'Seuils médailles';

    /** @param array<string, mixed> $fileThresholds values of the file, the originals */
    public function __construct(
        private readonly Connection $connection,
        #[Autowire('%match_medals.thresholds%')] private readonly array $fileThresholds,
    ) {
    }

    /** @return array<string, int|list<string>> the thresholds in force: the file, replaced by the admin's values */
    public function thresholds(): array
    {
        return MedalThresholds::fromArray([...$this->fileThresholds, ...$this->overrides()])->toArray();
    }

    /** @return array<string, int|list<string>> the original values, from the file */
    public function fileThresholds(): array
    {
        return MedalThresholds::fromArray($this->fileThresholds)->toArray();
    }

    /** @return array<string, int|list<string>> only what the admin replaced */
    public function overrides(): array
    {
        $stored = $this->read(self::THRESHOLDS);

        return array_intersect_key(is_array($stored) ? $stored : [], $this->fileThresholds());
    }

    /**
     * @param array<string, mixed> $changes
     *
     * @throws \InvalidArgumentException when a value is not valid: nothing is then saved
     */
    public function saveThresholds(array $changes, string $user): void
    {
        $valid = MedalThresholds::validate($changes);
        $before = $this->thresholds();
        $file = $this->fileThresholds();
        $overrides = [...$this->overrides(), ...$valid];
        foreach ($overrides as $name => $value) {
            if ($value === $file[$name]) {
                unset($overrides[$name]);
            }
        }
        $this->write(self::THRESHOLDS, $overrides, $user);
        $this->journal($user, 'Seuils modifiés : '.$this->differences($before, $this->thresholds()));
    }

    /** Back to the values of the file. */
    public function resetThresholds(string $user): void
    {
        $before = $this->thresholds();
        $this->write(self::THRESHOLDS, [], $user);
        $this->journal($user, 'Seuils rétablis : '.$this->differences($before, $this->thresholds()));
    }

    /** @return list<string> */
    public function deletedSeasons(): array
    {
        $stored = $this->read(self::DELETED_SEASONS);

        return is_array($stored) ? array_values(array_map('strval', $stored)) : [];
    }

    public function isDeleted(string $season): bool
    {
        return in_array($season, $this->deletedSeasons(), true);
    }

    public function markDeleted(string $season, string $user): void
    {
        $this->write(self::DELETED_SEASONS, array_values(array_unique([...$this->deletedSeasons(), $season])), $user);
    }

    public function unmarkDeleted(string $season, string $user): void
    {
        if ($this->isDeleted($season)) {
            $this->write(self::DELETED_SEASONS, array_values(array_diff($this->deletedSeasons(), [$season])), $user);
        }
    }

    /** @return array<string, mixed>|null state of the last full computation started from the admin */
    public function job(): ?array
    {
        $stored = $this->read(self::COMPUTE_JOB);

        return is_array($stored) ? $stored : null;
    }

    /** @param array<string, mixed> $job */
    public function saveJob(array $job): void
    {
        $this->write(self::COMPUTE_JOB, $job, (string) ($job['user'] ?? 'system'));
    }

    /** Journal entry of a season action (recalculation, deletion), written next to the threshold changes. */
    public function journalSeason(string $user, string $text): void
    {
        $this->journal($user, $text);
    }

    private function read(string $name): mixed
    {
        $json = $this->connection->fetchOne('SELECT value FROM kp_medal_setting WHERE name = ?', [$name]);

        return $json === false ? null : json_decode((string) $json, true);
    }

    private function write(string $name, mixed $value, string $user): void
    {
        $this->connection->executeStatement(
            'INSERT INTO kp_medal_setting (name, value, updated_at, updated_by) VALUES (?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = VALUES(updated_at), updated_by = VALUES(updated_by)',
            [$name, json_encode($value, JSON_THROW_ON_ERROR), mb_substr($user, 0, 20)],
        );
    }

    /**
     * @param array<string, mixed> $before
     * @param array<string, mixed> $after
     */
    private function differences(array $before, array $after): string
    {
        $changes = [];
        foreach ($after as $name => $value) {
            if ($before[$name] !== $value) {
                $changes[] = sprintf('%s : %s → %s', $name, json_encode($before[$name]), json_encode($value));
            }
        }

        return $changes === [] ? 'aucun changement' : implode(' ; ', $changes);
    }

    private function journal(string $user, string $text): void
    {
        $this->connection->insert('kp_journal', [
            'Dates' => date('Y-m-d H:i:s'),
            'Users' => $user,
            'Actions' => self::JOURNAL_ACTION,
            'Journal' => mb_substr($text, 0, 60000),
        ]);
    }
}
