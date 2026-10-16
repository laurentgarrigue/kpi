<?php

namespace App\Cms;

use Doctrine\DBAL\Connection;

/** Les 4 catégories fixes des actualités (FEATURE_CMS.md § 3.1). */
final class CategoryRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> dans l'ordre d'affichage */
    public function all(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM kp_cms_category ORDER BY position');
    }

    public function idOfSlug(string $slug): ?int
    {
        $id = $this->connection->fetchOne('SELECT id FROM kp_cms_category WHERE slug = ?', [$slug]);

        return $id === false ? null : (int) $id;
    }

    /** @return list<int> */
    public function ids(): array
    {
        return array_map('intval', $this->connection->fetchFirstColumn('SELECT id FROM kp_cms_category'));
    }
}
