<?php

namespace App\Command;

use App\Discipline\CardSuspensionService;
use App\Service\ActiveSeason;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Cumuls de cartons → suspensions notifiées, lancé par cron toutes les 10 min (FEATURE_CARD_SUSPENSIONS.md S13). */
#[AsCommand(name: 'app:card-suspensions:sync', description: 'Detect card accumulations, record and notify new suspensions, cancel those a corrected sheet removed')]
final class CardSuspensionsSyncCommand extends Command
{
    public function __construct(
        private readonly CardSuspensionService $suspensions,
        private readonly ActiveSeason $activeSeason,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('season', null, InputOption::VALUE_REQUIRED, 'Season to process (default: the active season)')
            ->addOption('silent', null, InputOption::VALUE_NONE, 'Record without any email nor appeal jury draft (catch-up of a season under way)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $season = (string) ($input->getOption('season') ?? $this->activeSeason->code());
        if (!preg_match('/^\d{4}$/', $season)) {
            $io->error('--season expects a 4-digit season');

            return Command::INVALID;
        }
        $result = $this->suspensions->sync($season, (bool) $input->getOption('silent'));
        $io->writeln(sprintf('%s card suspensions %s: %d detected, %d notified, %d cancelled', date('Y-m-d H:i'), $season, $result['detected'], $result['notified'], $result['cancelled']));

        return Command::SUCCESS;
    }
}
