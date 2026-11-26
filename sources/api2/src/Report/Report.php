<?php

namespace App\Report;

/** Une ligne de kp_report (FEATURE_DESIGNATIONS.md § 8.1). */
final class Report
{
    public const DRAFT = 'BROUILLON';
    public const SUBMITTED = 'SOUMIS';

    /** @param array<string, mixed> $answers */
    public function __construct(
        public readonly int $id,
        public readonly string $type,
        public readonly int $version,
        public readonly ReportScope $scope,
        public readonly string $scopeId,
        public readonly string $season,
        public readonly ?int $authorMatric,
        public readonly string $enteredBy,
        public readonly string $state,
        public readonly ?string $submittedAt,
        public readonly bool $late,
        public readonly array $answers,
    ) {
    }

    public function definition(): ReportTypeDefinition
    {
        return ReportTypeCatalog::version($this->type, $this->version);
    }
}
