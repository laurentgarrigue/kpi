<?php

namespace App\Command;

use App\MatchMedals\MatchMedalService;
use App\MatchMedals\MedalComputeJob;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/** Full computation of one season with the current thresholds (FEATURE_MATCH_MEDALS.md § 6.3). */
#[AsCommand(name: 'app:match-medals:compute', description: 'Compute the match medals of one season with the current thresholds')]
final class MatchMedalsComputeCommand extends Command
{
    public function __construct(private readonly MatchMedalService $medals, private readonly MedalComputeJob $job)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('season', null, InputOption::VALUE_REQUIRED, 'Season to compute, e.g. 2026');
        $this->addOption('user', null, InputOption::VALUE_REQUIRED, 'Who asked (journal and job state)', 'system');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $season = (string) $input->getOption('season');
        if (preg_match('/^\d{4}$/', $season) !== 1) {
            $io->error('--season=YYYY is required (one season at a time: the other seasons keep their thresholds).');

            return Command::INVALID;
        }
        $this->job->run($season, (string) $input->getOption('user'));
        $result = ['computed' => (int) ($this->job->status()['computed'] ?? 0), 'removed' => 0];
        $io->success(sprintf('Season %s: %d validated games computed (rules v%d).', $season, $result['computed'], $this->medals->rulesVersion()));

        return Command::SUCCESS;
    }
}
