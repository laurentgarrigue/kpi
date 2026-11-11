<?php

namespace App\Tests\Support;

use App\MatchMedals\MedalComputeJob;
use App\MatchMedals\SeasonComputeLauncher;

/** Integration tests compute in the request: the test transaction cannot be shared with another process. */
final class InlineSeasonComputeLauncher implements SeasonComputeLauncher
{
    public function __construct(private readonly MedalComputeJob $job)
    {
    }

    public function launch(string $season, string $user): void
    {
        $this->job->run($season, $user);
    }
}
