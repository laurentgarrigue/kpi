<?php

namespace App\AgeCategory;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/**
 * Data migration of the age category (CATEGORIE_AGE.md stories 31-34): the conventional birth dates become unknown
 * (NULL), then the Categ cache of every composition is recomputed, locked competitions included (ADR 0001).
 * Idempotent, so it doubles as a safety net after an entry through the legacy interface. Surclassements are not touched.
 */
final class AgeCategoryMigration
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CompositionAgeCategories $compositions,
    ) {
    }

    /**
     * What run() would do, without writing anything. The rule already reads a conventional date as unknown, so the
     * planned category changes are the ones that follow the date conversion.
     */
    public function plan(): AgeCategoryMigrationReport
    {
        return new AgeCategoryMigrationReport($this->countConventionalBirthDates(), $this->compositions->planAll());
    }

    public function run(): AgeCategoryMigrationReport
    {
        return $this->connection->transactional(function (): AgeCategoryMigrationReport {
            $convertedDates = $this->connection->executeStatement(
                'UPDATE kp_licence SET Naissance = NULL WHERE Naissance IN (?)',
                [AgeCategoryRule::CONVENTIONAL_BIRTH_DATES],
                [ArrayParameterType::STRING]
            );
            $changes = $this->compositions->planAll();
            $this->compositions->apply($changes);

            return new AgeCategoryMigrationReport((int) $convertedDates, $changes);
        });
    }

    private function countConventionalBirthDates(): int
    {
        return (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM kp_licence WHERE Naissance IN (?)',
            [AgeCategoryRule::CONVENTIONAL_BIRTH_DATES],
            [ArrayParameterType::STRING]
        );
    }
}
