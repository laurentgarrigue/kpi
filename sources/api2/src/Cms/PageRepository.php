<?php

namespace App\Cms;

use Doctrine\DBAL\Connection;

/** Accès à kp_cms_page (FEATURE_CMS.md § 3, PAGE_CONTENT.md). */
final class PageRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM kp_cms_page WHERE id = ?', [$id]);

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findBySlug(string $slug): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM kp_cms_page WHERE slug = ?', [$slug]);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> toutes les pages (arborescence, chemins, administration) */
    public function all(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM kp_cms_page ORDER BY title_fr, id');
    }

    /** @return list<array<string, mixed>> pages publiques dont le titre contient le terme */
    public function searchPublic(string $pattern, int $limit): array
    {
        return $this->connection->fetchAllAssociative(
            "SELECT * FROM kp_cms_page WHERE status = 'published' AND (title_fr LIKE ? OR title_en LIKE ?) ORDER BY title_fr LIMIT $limit",
            [$pattern, $pattern],
        );
    }

    public function findIdByLegacyId(int $wordPressId): ?int
    {
        $id = $this->connection->fetchOne('SELECT id FROM kp_cms_page WHERE legacy_wp_id = ?', [$wordPressId]);

        return $id === false ? null : (int) $id;
    }

    public function slugExists(string $slug, ?int $exceptId): bool
    {
        return $this->connection->fetchOne('SELECT 1 FROM kp_cms_page WHERE slug = ? AND id <> ?', [$slug, $exceptId ?? 0]) !== false;
    }

    /** @param array<string, mixed> $values */
    public function insert(array $values): int
    {
        $this->connection->insert('kp_cms_page', $values);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, mixed> $values */
    public function update(int $id, array $values): void
    {
        $this->connection->update('kp_cms_page', $values, ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->connection->update('kp_cms_page', ['parent_id' => null], ['parent_id' => $id]);
        $this->connection->delete('kp_cms_page', ['id' => $id]);
    }
}
