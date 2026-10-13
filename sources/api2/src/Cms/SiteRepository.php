<?php

namespace App\Cms;

use Doctrine\DBAL\Connection;

/** Menu éditorial, blocs d'accueil et redirections (FEATURE_CMS.md § 3). */
final class SiteRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return list<array<string, mixed>> entrées du menu, parents d'abord puis par position */
    public function menuItems(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM kp_cms_menu_item ORDER BY parent_id IS NOT NULL, position, id');
    }

    /** @param list<array{label_fr: string, label_en: string, target_kind: string, target_ref: string, children: list<array<string, mixed>>}> $items */
    public function replaceMenu(array $items): void
    {
        $this->connection->executeStatement('DELETE FROM kp_cms_menu_item');
        $this->insertMenuItems($items, null);
    }

    /** @param list<array<string, mixed>> $items entrées d'un niveau, avec leurs `children` */
    private function insertMenuItems(array $items, ?int $parentId): void
    {
        foreach ($items as $position => $item) {
            $id = $this->insertMenuItem($item, $parentId, $position + 1);
            $this->insertMenuItems($item['children'] ?? [], $id);
        }
    }

    /** @return list<array<string, mixed>> */
    public function homeBlocks(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM kp_cms_home_block ORDER BY position, id');
    }

    /** @param array{position: int, enabled: bool, config: array<string, mixed>} $values */
    public function updateHomeBlock(int $id, array $values): void
    {
        $this->connection->update('kp_cms_home_block', [
            'position' => $values['position'],
            'enabled' => $values['enabled'] ? 1 : 0,
            'config' => json_encode($values['config'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ], ['id' => $id]);
    }

    /** @return array<string, mixed>|null */
    public function redirectFor(string $path): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM kp_cms_redirect WHERE from_path = ?', [$path]);

        return $row === false ? null : $row;
    }

    public function countRedirectHit(int $id): void
    {
        $this->connection->executeStatement('UPDATE kp_cms_redirect SET hits = hits + 1 WHERE id = ?', [$id]);
    }

    /** @return list<array<string, mixed>> */
    public function redirects(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM kp_cms_redirect ORDER BY from_path');
    }

    public function saveRedirect(string $from, string $to): int
    {
        $this->connection->executeStatement(
            'INSERT INTO kp_cms_redirect (from_path, to_path, code) VALUES (?, ?, 301) ON DUPLICATE KEY UPDATE to_path = VALUES(to_path)',
            [$from, $to],
        );

        return (int) $this->connection->fetchOne('SELECT id FROM kp_cms_redirect WHERE from_path = ?', [$from]);
    }

    public function deleteRedirect(int $id): bool
    {
        return $this->connection->delete('kp_cms_redirect', ['id' => $id]) > 0;
    }

    /** @param array<string, mixed> $item */
    private function insertMenuItem(array $item, ?int $parentId, int $position): int
    {
        $this->connection->insert('kp_cms_menu_item', [
            'parent_id' => $parentId,
            'position' => $position,
            'label_fr' => $item['label_fr'],
            'label_en' => $item['label_en'],
            'target_kind' => $item['target_kind'],
            'target_ref' => $item['target_ref'],
        ]);

        return (int) $this->connection->lastInsertId();
    }
}
