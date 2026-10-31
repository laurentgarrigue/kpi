<?php

namespace App\AgeCategory;

use Doctrine\DBAL\Connection;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Category table read from kp_categorie, once per request: the FrankenPHP worker resets it between requests
 * (kernel.reset), so a change of the table is seen by the next request.
 */
final class DbalAgeCategoryTable implements AgeCategoryTable, ResetInterface
{
    /** @var list<AgeCategory>|null */
    private ?array $categories = null;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function all(): array
    {
        return $this->categories ??= array_map(
            static fn (array $row): AgeCategory => new AgeCategory((string) $row['id'], (string) $row['libelle'], (int) $row['age_min'], (int) $row['age_max']),
            $this->connection->fetchAllAssociative('SELECT id, libelle, age_min, age_max FROM kp_categorie ORDER BY age_min, id')
        );
    }

    public function reset(): void
    {
        $this->categories = null;
    }
}
