<?php

namespace App\MatchMedals;

/**
 * A full computation of a season started from the admin (FEATURE_MATCH_MEDALS.md § 6.3, MED-73): it takes minutes, so
 * it runs in its own process; its state (running, done, error) is stored for the page to poll. One at a time.
 */
final class MedalComputeJob
{
    /** A job that has not ended after this long is reported as failed (its process died). */
    private const STALE_AFTER_SECONDS = 3600;

    public function __construct(private readonly MedalSettings $settings, private readonly MatchMedalService $medals)
    {
    }

    /** @return array<string, mixed>|null */
    public function status(): ?array
    {
        $job = $this->settings->job();
        if ($job !== null && $job['status'] === 'running' && time() - (int) strtotime((string) $job['started_at']) > self::STALE_AFTER_SECONDS) {
            $job = [...$job, 'status' => 'error', 'message' => 'interrupted', 'finished_at' => date('Y-m-d H:i:s')];
            $this->settings->saveJob($job);
        }

        return $job;
    }

    public function isRunning(): bool
    {
        return ($this->status()['status'] ?? null) === 'running';
    }

    /** Marks the job as started; the caller then launches {@see run()} in another process. */
    public function start(string $season, string $user): void
    {
        $this->settings->saveJob(['season' => $season, 'status' => 'running', 'user' => $user, 'started_at' => date('Y-m-d H:i:s')]);
    }

    /** The computation itself (the command runs it): keeps the state and the journal up to date. */
    public function run(string $season, string $user): void
    {
        $job = ['season' => $season, 'user' => $user, 'started_at' => $this->settings->job()['started_at'] ?? date('Y-m-d H:i:s')];
        try {
            $result = $this->medals->computeSeason($season, $user);
            $this->settings->journalSeason($user, sprintf('Saison %s recalculée : %d matchs', $season, $result['computed']));
            $this->settings->saveJob([...$job, 'status' => 'done', 'computed' => $result['computed'], 'finished_at' => date('Y-m-d H:i:s')]);
        } catch (\Throwable $e) {
            $this->settings->saveJob([...$job, 'status' => 'error', 'message' => mb_substr($e->getMessage(), 0, 300), 'finished_at' => date('Y-m-d H:i:s')]);
            throw $e;
        }
    }
}
