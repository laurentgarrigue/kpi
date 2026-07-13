<?php

namespace App\Command;

use App\Service\EventCacheService;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Event Cache Worker — background daemon
 *
 * Long-running process that reads its configuration from kp_event_worker_config
 * (driven by the app4 "Event Cache Manager" page via AdminEventWorkerController)
 * and continuously regenerates the live cache JSON files
 * (event<id>_pitch<pitch>.json + on-demand <idMatch>_match_global.json).
 *
 * Replaces the legacy live/event_worker.php daemon.
 *
 * Usage:
 *   php bin/console app:event-cache-worker          # run forever
 *   php bin/console app:event-cache-worker --once    # single pass (cron / debug)
 */
#[AsCommand(
    name: 'app:event-cache-worker',
    description: 'Background worker generating live cache JSON files for active events'
)]
class EventCacheWorkerCommand extends Command
{
    private bool $running = true;

    /** @var array<int, array{startTime: int, initialTime: int, idEvent: int}> */
    private array $eventStates = [];

    public function __construct(
        private readonly Connection $connection,
        private readonly EventCacheService $eventCache,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('once', null, InputOption::VALUE_NONE, 'Run a single pass then exit (cron / debug mode)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io   = new SymfonyStyle($input, $output);
        $once = (bool) $input->getOption('once');

        $this->registerSignalHandlers($io);

        $io->title('Event Cache Worker started (PID ' . getmypid() . ')');

        while ($this->running) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            try {
                $delay = $this->tick($io);
            } catch (\Throwable $e) {
                $io->error('Worker error: ' . $e->getMessage());
                $delay = 10;
            }

            if ($once) {
                break;
            }

            sleep($delay);
        }

        $io->writeln('Event Cache Worker stopped');

        return Command::SUCCESS;
    }

    /**
     * One iteration: process every active config, generate caches, send heartbeats.
     *
     * @return int seconds to wait before the next iteration
     */
    private function tick(SymfonyStyle $io): int
    {
        $configs = $this->fetchActiveConfigs();

        if (empty($configs)) {
            $this->eventStates = [];

            return 10;
        }

        foreach ($configs as $config) {
            // Paused configs keep their state but generate nothing
            if ($config['status'] !== 'running') {
                continue;
            }

            $this->processConfig($config, $io);
        }

        // Drop states for configs that are no longer active
        $activeIds = array_map(static fn (array $c): int => (int) $c['id'], $configs);
        foreach (array_keys($this->eventStates) as $stateId) {
            if (!in_array($stateId, $activeIds, true)) {
                unset($this->eventStates[$stateId]);
            }
        }

        // Wait the smallest delay among active configs (min 1s)
        $minDelay = min(array_map(static fn (array $c): int => (int) $c['delay_event'], $configs));

        return max(1, $minDelay);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function processConfig(array $config, SymfonyStyle $io): void
    {
        $configId = (int) $config['id'];
        $idEvent  = (int) $config['id_event'];

        // Clock origin = created_at (reset to NOW() on every start by the controller).
        // Using the persisted created_at instead of an in-process microtime() ensures
        // the daemon's simulated clock stays identical to the one AdminEventWorkerController
        // shows on the app4 card & monitor — same anchor, no drift between the two.
        $startTime   = (int) strtotime($config['created_at']);
        $initialTime = (int) strtotime($config['date_event'] . ' ' . $config['hour_event_initial']);

        if (!isset($this->eventStates[$configId])) {
            $this->eventStates[$configId] = [
                'startTime'   => $startTime,
                'initialTime' => $initialTime,
                'idEvent'     => $idEvent,
            ];
            $io->writeln(sprintf(
                'Event #%d started — Date: %s — Initial: %s',
                $idEvent,
                $config['date_event'],
                $config['hour_event_initial']
            ));
        }

        // Simulated current time = initial event time + real seconds elapsed since
        // created_at (the shared clock origin). Both the DATE and the hour are derived
        // from this timestamp, so a worker started on day 1 naturally rolls over to the
        // following match days as real time passes (a multi-day event stayed stuck on
        // date_event otherwise, only the hour advanced).
        $elapsed              = max(0, time() - $startTime);
        $currentSimulatedTime = $initialTime + $elapsed;
        $dateEventWork        = date('Y-m-d', $currentSimulatedTime);
        $currentHourEvent     = date('H:i', $currentSimulatedTime);

        // Apply the warm-up offset to compute the "working" time used for selection
        $workMinutes   = $this->eventCache->hhmmToMinutes($currentHourEvent) + (int) $config['offset_event'];
        $hourEventWork = $this->eventCache->minutesToHhmm($workMinutes);

        $pitches = [];
        $pitchEvent = (int) $config['pitch_event'];
        for ($i = 1; $i <= $pitchEvent; $i++) {
            $pitches[] = $i;
        }

        try {
            $this->eventCache->generateEvent(
                $idEvent,
                $dateEventWork,
                $hourEventWork,
                $currentHourEvent,
                $pitches
            );
            $this->sendHeartbeat($configId, null);
        } catch (\Throwable $e) {
            $io->warning(sprintf('Event #%d generation error: %s', $idEvent, $e->getMessage()));
            $this->sendHeartbeat($configId, $e->getMessage());
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchActiveConfigs(): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT * FROM kp_event_worker_config
             WHERE status IN ('running', 'paused')
             ORDER BY id_event ASC"
        );
    }

    private function sendHeartbeat(int $configId, ?string $errorMessage): void
    {
        try {
            $this->connection->executeStatement(
                'UPDATE kp_event_worker_config
                 SET last_execution = NOW(),
                     execution_count = execution_count + 1,
                     error_message = ?,
                     updated_at = NOW()
                 WHERE id = ?',
                [$errorMessage, $configId]
            );
        } catch (\Throwable) {
            // Heartbeat failures must not kill the worker loop
        }
    }

    private function registerSignalHandlers(SymfonyStyle $io): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }

        $handler = function () use ($io): void {
            $io->writeln('Signal received, shutting down gracefully…');
            $this->running = false;
        };

        pcntl_signal(SIGTERM, $handler);
        pcntl_signal(SIGINT, $handler);
    }
}
