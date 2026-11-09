<?php

namespace App\Command;

use App\MatchMedals\MatchMedalService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Incremental update, run by cron: new or changed validated games, unvalidated ones (§ 6.3). */
#[AsCommand(name: 'app:match-medals:sync', description: 'Compute the medals of new or changed validated games, remove those of unvalidated games')]
final class MatchMedalsSyncCommand extends Command
{
    public function __construct(private readonly MatchMedalService $medals)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->medals->sync();
        (new SymfonyStyle($input, $output))->writeln(sprintf('%s match medals: %d computed, %d removed', date('Y-m-d H:i'), $result['computed'], $result['removed']));

        return Command::SUCCESS;
    }
}
