<?php

namespace App\Scoring;

/**
 * Clock an event's matches are picked on.
 *
 * When the Event Cache Manager runs for an event it simulates the event's time: the
 * initial date/hour of the config plus the real seconds elapsed since the worker
 * started (`created_at`, reset on every start). Both the date and the hour derive from
 * that timestamp, so a multi-day event rolls over to the next match day. Without a
 * worker, the event runs on the real clock.
 *
 * `workHour` is the hour used to pick the current match: the simulated hour plus the
 * warm-up offset, so the next match shows up while the teams warm up (legacy rule).
 */
final class EventClock
{
    private function __construct(
        public readonly string $date,
        public readonly string $hour,
        public readonly string $workHour,
    ) {
    }

    /**
     * @param array{date_event:string,hour_event_initial:string,offset_event:int|string,created_at:string} $config
     *        a kp_event_worker_config row
     */
    public static function fromWorkerConfig(array $config, int $now): self
    {
        $startedAt = (int) strtotime($config['created_at']);
        $initialTime = (int) strtotime($config['date_event'] . ' ' . $config['hour_event_initial']);
        $simulated = $initialTime + max(0, $now - $startedAt);

        return new self(
            date('Y-m-d', $simulated),
            date('H:i', $simulated),
            date('H:i', $simulated + 60 * (int) $config['offset_event']),
        );
    }

    public static function real(int $now): self
    {
        return new self(date('Y-m-d', $now), date('H:i', $now), date('H:i', $now));
    }
}
