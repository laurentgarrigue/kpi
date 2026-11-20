<?php

namespace App\Security;

/** Décision de {@see SecondFactorPolicy::decide()} et, pendant la grâce, ses dates. */
final class SecondFactorOutcome
{
    public function __construct(
        public readonly SecondFactorDecision $decision,
        public readonly ?\DateTimeImmutable $graceStartedAt = null,
        public readonly ?\DateTimeImmutable $enrollmentDueAt = null,
    ) {
    }
}
