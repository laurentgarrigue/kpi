<?php

namespace App\MatchMedals;

/** Runs the full computation of a season apart from the request that asked for it. */
interface SeasonComputeLauncher
{
    public function launch(string $season, string $user): void;
}
