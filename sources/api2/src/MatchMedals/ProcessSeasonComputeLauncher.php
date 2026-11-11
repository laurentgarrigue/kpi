<?php

namespace App\MatchMedals;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

/** Starts `bin/console app:match-medals:compute` in the background: it goes on after the response, and survives a worker restart. */
final class ProcessSeasonComputeLauncher implements SeasonComputeLauncher
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir)
    {
    }

    public function launch(string $season, string $user): void
    {
        $command = sprintf(
            'nohup php bin/console app:match-medals:compute --season=%s --user=%s > /dev/null 2>&1 &',
            escapeshellarg($season),
            escapeshellarg($user),
        );
        Process::fromShellCommandline($command, $this->projectDir)->run();
    }
}
