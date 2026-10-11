<?php

namespace App\Cms;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Accès à kp_cms_media et kp_cms_album (FEATURE_CMS.md § 3, § 5.3). */
final class MediaRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM kp_cms_media WHERE id = ?', [$id]);

        return $row === false ? null : $row;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array<string, mixed>> médias par id
     */
    public function findByIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids)));
        if ($ids === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative('SELECT * FROM kp_cms_media WHERE id IN (?)', [$ids], [ArrayParameterType::INTEGER]);
        $byId = [];
        foreach ($rows as $row) {
            $byId[(int) $row['id']] = $row;
        }

        return $byId;
    }

    /** @return list<array<string, mixed>> */
    public function list(?string $type, ?int $albumId, int $limit, int $offset): array
    {
        [$where, $params] = self::filters($type, $albumId);

        return $this->connection->fetchAllAssociative("SELECT * FROM kp_cms_media WHERE $where ORDER BY created_at DESC, id DESC LIMIT $limit OFFSET $offset", $params);
    }

    public function count(?string $type, ?int $albumId): int
    {
        [$where, $params] = self::filters($type, $albumId);

        return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_cms_media WHERE $where", $params);
    }

    public function pathExists(string $path): bool
    {
        return $this->connection->fetchOne('SELECT 1 FROM kp_cms_media WHERE path = ?', [$path]) !== false;
    }

    /** @param array<string, mixed> $values */
    public function insert(array $values): int
    {
        $this->connection->insert('kp_cms_media', $values);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, mixed> $values */
    public function update(int $id, array $values): void
    {
        $this->connection->update('kp_cms_media', $values, ['id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->connection->delete('kp_cms_media', ['id' => $id]);
    }

    /** Le média est-il utilisé (image de une, couverture d'album, contenu, bloc d'accueil) ? */
    public function isUsed(int $id, string $path): bool
    {
        $pattern = '%' . addcslashes(MediaUrls::url($path), '%_\\') . '%';
        $idPattern = '%' . $id . '%';

        return $this->connection->fetchOne(
            "SELECT 1 FROM kp_cms_article WHERE cover_media_id = ? OR body_fr LIKE ? OR body_en LIKE ?
            UNION SELECT 1 FROM kp_cms_page WHERE body_fr LIKE ? OR body_en LIKE ?
            UNION SELECT 1 FROM kp_cms_album WHERE cover_media_id = ?
            UNION SELECT 1 FROM kp_cms_home_block WHERE kind = 'partners' AND config LIKE ?
            LIMIT 1",
            [$id, $pattern, $pattern, $pattern, $pattern, $id, $idPattern],
        ) !== false;
    }

    // ---------------------------------------------------------------- albums

    /** @return list<array<string, mixed>> */
    public function albums(): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT a.*, (SELECT COUNT(*) FROM kp_cms_media m WHERE m.album_id = a.id) AS media_count FROM kp_cms_album a ORDER BY a.title_fr',
        );
    }

    /** @return array<string, mixed>|null */
    public function findAlbum(int $id): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM kp_cms_album WHERE id = ?', [$id]);

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function findAlbumBySlug(string $slug): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM kp_cms_album WHERE slug = ?', [$slug]);

        return $row === false ? null : $row;
    }

    /** @return list<array<string, mixed>> */
    public function albumMedia(int $albumId): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM kp_cms_media WHERE album_id = ? ORDER BY created_at, id', [$albumId]);
    }

    public function albumSlugExists(string $slug, ?int $exceptId): bool
    {
        return $this->connection->fetchOne('SELECT 1 FROM kp_cms_album WHERE slug = ? AND id <> ?', [$slug, $exceptId ?? 0]) !== false;
    }

    /** @param array<string, mixed> $values */
    public function insertAlbum(array $values): int
    {
        $this->connection->insert('kp_cms_album', $values);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, mixed> $values */
    public function updateAlbum(int $id, array $values): void
    {
        $this->connection->update('kp_cms_album', $values, ['id' => $id]);
    }

    public function deleteAlbum(int $id): void
    {
        $this->connection->update('kp_cms_media', ['album_id' => null], ['album_id' => $id]);
        $this->connection->delete('kp_cms_album', ['id' => $id]);
    }

    /** @return array{string, list<mixed>} */
    private static function filters(?string $type, ?int $albumId): array
    {
        $conditions = ['1 = 1'];
        $params = [];
        if ($type === 'image') {
            $conditions[] = "mime LIKE 'image/%'";
        } elseif ($type === 'document') {
            $conditions[] = "mime NOT LIKE 'image/%'";
        }
        if ($albumId !== null) {
            $conditions[] = 'album_id = ?';
            $params[] = $albumId;
        }

        return [implode(' AND ', $conditions), $params];
    }
}
