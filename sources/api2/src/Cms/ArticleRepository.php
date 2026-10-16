<?php

namespace App\Cms;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;

/** Accès à kp_cms_article et à ses catégories / liens (FEATURE_CMS.md § 3). */
final class ArticleRepository
{
    private const PUBLIC_CONDITION = "a.status = 'published' AND a.published_at IS NOT NULL AND a.published_at <= ?";
    private const NEWEST_FIRST = 'a.published_at DESC, a.id DESC';

    public const LINK_KINDS = ['competition', 'event', 'club'];

    public function __construct(private readonly Connection $connection)
    {
    }

    // ---------------------------------------------------------------- public

    /**
     * Articles publics, du plus récent au plus ancien, filtrés par catégorie et / ou lien.
     *
     * @return list<array<string, mixed>>
     */
    public function findPublic(string $now, ?int $categoryId, ?string $linkKind, ?string $linkRef, int $limit, int $offset): array
    {
        [$where, $params] = $this->publicFilters($now, $categoryId, $linkKind, $linkRef);

        return $this->connection->fetchAllAssociative(
            "SELECT a.* FROM kp_cms_article a WHERE $where ORDER BY " . self::NEWEST_FIRST . " LIMIT $limit OFFSET $offset",
            $params,
        );
    }

    public function countPublic(string $now, ?int $categoryId, ?string $linkKind, ?string $linkRef): int
    {
        [$where, $params] = $this->publicFilters($now, $categoryId, $linkKind, $linkRef);

        return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_cms_article a WHERE $where", $params);
    }

    /** @return array<string, mixed>|null */
    public function findPublicBySlug(string $slug, string $now): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT a.* FROM kp_cms_article a WHERE a.slug = ? AND ' . self::PUBLIC_CONDITION, [$slug, $now]);

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null quel que soit le statut (prévisualisation, administration) */
    public function findBySlug(string $slug): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT a.* FROM kp_cms_article a WHERE a.slug = ?', [$slug]);

        return $row === false ? null : $row;
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $row = $this->connection->fetchAssociative('SELECT a.* FROM kp_cms_article a WHERE a.id = ?', [$id]);

        return $row === false ? null : $row;
    }

    /**
     * Article public voisin, plus récent (`newer`) ou plus ancien, pour la navigation précédent / suivant.
     *
     * @param array<string, mixed> $article
     *
     * @return array<string, mixed>|null
     */
    public function findPublicNeighbour(array $article, string $now, bool $newer): ?array
    {
        $comparison = $newer
            ? '(a.published_at > ? OR (a.published_at = ? AND a.id > ?))'
            : '(a.published_at < ? OR (a.published_at = ? AND a.id < ?))';
        $order = $newer ? 'a.published_at ASC, a.id ASC' : self::NEWEST_FIRST;
        $row = $this->connection->fetchAssociative(
            'SELECT a.slug, a.title_fr, a.title_en FROM kp_cms_article a WHERE ' . self::PUBLIC_CONDITION . " AND $comparison ORDER BY $order LIMIT 1",
            [$now, $article['published_at'], $article['published_at'], (int) $article['id']],
        );

        return $row === false ? null : $row;
    }

    /**
     * @param list<int> $ids
     *
     * @return list<array<string, mixed>> articles publics parmi ces ids, dans l'ordre donné
     */
    public function findPublicByIds(array $ids, string $now): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT a.* FROM kp_cms_article a WHERE a.id IN (?) AND ' . self::PUBLIC_CONDITION,
            [$ids, $now],
            [ArrayParameterType::INTEGER],
        );
        $byId = array_column($rows, null, 'id');

        return array_values(array_filter(array_map(static fn (int $id): ?array => $byId[$id] ?? null, $ids)));
    }

    /** @return list<array<string, mixed>> articles publics dont le titre ou le chapô contient le terme */
    public function searchPublic(string $pattern, string $now, int $limit): array
    {
        return $this->connection->fetchAllAssociative(
            'SELECT a.* FROM kp_cms_article a WHERE ' . self::PUBLIC_CONDITION . '
            AND (a.title_fr LIKE ? OR a.title_en LIKE ? OR a.lead_fr LIKE ?)
            ORDER BY ' . self::NEWEST_FIRST . " LIMIT $limit",
            [$now, $pattern, $pattern, $pattern],
        );
    }

    /**
     * @param list<int> $articleIds
     *
     * @return array<int, list<array<string, mixed>>> catégories de chaque article, dans l'ordre des catégories
     */
    public function categoriesOf(array $articleIds): array
    {
        if ($articleIds === []) {
            return [];
        }
        $rows = $this->connection->fetchAllAssociative(
            'SELECT ac.article_id, c.* FROM kp_cms_article_category ac INNER JOIN kp_cms_category c ON c.id = ac.category_id
            WHERE ac.article_id IN (?) ORDER BY c.position',
            [$articleIds],
            [ArrayParameterType::INTEGER],
        );
        $byArticle = [];
        foreach ($rows as $row) {
            $byArticle[(int) $row['article_id']][] = $row;
        }

        return $byArticle;
    }

    /** @return list<array{kind: string, ref: string}> */
    public function linksOf(int $articleId): array
    {
        return array_map(
            static fn (array $row): array => ['kind' => (string) $row['kind'], 'ref' => (string) $row['ref']],
            $this->connection->fetchAllAssociative(
                "SELECT kind, ref FROM kp_cms_article_link WHERE article_id = ? ORDER BY FIELD(kind, 'competition', 'event', 'club'), ref",
                [$articleId],
            ),
        );
    }

    // ---------------------------------------------------------------- administration

    /**
     * @return list<array<string, mixed>>
     */
    public function listForAdmin(?string $status, ?int $categoryId, ?string $search, string $now, int $limit, int $offset): array
    {
        [$where, $params] = $this->adminFilters($status, $categoryId, $search, $now);

        return $this->connection->fetchAllAssociative(
            "SELECT a.id, a.slug, a.title_fr, a.status, a.published_at, a.updated_at, a.updated_by, a.legacy_wp_id
            FROM kp_cms_article a WHERE $where
            ORDER BY COALESCE(a.published_at, a.updated_at) DESC, a.id DESC LIMIT $limit OFFSET $offset",
            $params,
        );
    }

    public function countForAdmin(?string $status, ?int $categoryId, ?string $search, string $now): int
    {
        [$where, $params] = $this->adminFilters($status, $categoryId, $search, $now);

        return (int) $this->connection->fetchOne("SELECT COUNT(*) FROM kp_cms_article a WHERE $where", $params);
    }

    public function findIdByLegacyId(int $wordPressId): ?int
    {
        $id = $this->connection->fetchOne('SELECT id FROM kp_cms_article WHERE legacy_wp_id = ?', [$wordPressId]);

        return $id === false ? null : (int) $id;
    }

    public function slugExists(string $slug, ?int $exceptId): bool
    {
        return $this->connection->fetchOne('SELECT 1 FROM kp_cms_article WHERE slug = ? AND id <> ?', [$slug, $exceptId ?? 0]) !== false;
    }

    /** @param array<string, mixed> $values colonnes de kp_cms_article */
    public function insert(array $values): int
    {
        $this->connection->insert('kp_cms_article', $values);

        return (int) $this->connection->lastInsertId();
    }

    /** @param array<string, mixed> $values */
    public function update(int $id, array $values): void
    {
        $this->connection->update('kp_cms_article', $values, ['id' => $id]);
    }

    /** @param list<int> $categoryIds */
    public function replaceCategories(int $articleId, array $categoryIds): void
    {
        $this->connection->delete('kp_cms_article_category', ['article_id' => $articleId]);
        foreach (array_unique($categoryIds) as $categoryId) {
            $this->connection->insert('kp_cms_article_category', ['article_id' => $articleId, 'category_id' => $categoryId]);
        }
    }

    /** @param list<array{kind: string, ref: string}> $links */
    public function replaceLinks(int $articleId, array $links): void
    {
        $this->connection->delete('kp_cms_article_link', ['article_id' => $articleId]);
        foreach (array_unique($links, SORT_REGULAR) as $link) {
            $this->connection->insert('kp_cms_article_link', ['article_id' => $articleId, 'kind' => $link['kind'], 'ref' => $link['ref']]);
        }
    }

    public function delete(int $id): void
    {
        $this->connection->delete('kp_cms_article_category', ['article_id' => $id]);
        $this->connection->delete('kp_cms_article_link', ['article_id' => $id]);
        $this->connection->delete('kp_cms_article', ['id' => $id]);
    }

    // ---------------------------------------------------------------- privé

    /** @return array{string, list<mixed>} */
    private function publicFilters(string $now, ?int $categoryId, ?string $linkKind, ?string $linkRef): array
    {
        $where = self::PUBLIC_CONDITION;
        $params = [$now];
        if ($categoryId !== null) {
            $where .= ' AND EXISTS (SELECT 1 FROM kp_cms_article_category ac WHERE ac.article_id = a.id AND ac.category_id = ?)';
            $params[] = $categoryId;
        }
        if ($linkKind !== null && $linkRef !== null) {
            $where .= ' AND EXISTS (SELECT 1 FROM kp_cms_article_link al WHERE al.article_id = a.id AND al.kind = ? AND al.ref = ?)';
            $params[] = $linkKind;
            $params[] = $linkRef;
        }

        return [$where, $params];
    }

    /** @return array{string, list<mixed>} */
    private function adminFilters(?string $status, ?int $categoryId, ?string $search, string $now): array
    {
        $conditions = ['1 = 1'];
        $params = [];
        if ($status === 'scheduled') {
            $conditions[] = "a.status = 'published' AND a.published_at > ?";
            $params[] = $now;
        } elseif ($status !== null) {
            $conditions[] = 'a.status = ?';
            $params[] = $status;
        }
        if ($categoryId !== null) {
            $conditions[] = 'EXISTS (SELECT 1 FROM kp_cms_article_category ac WHERE ac.article_id = a.id AND ac.category_id = ?)';
            $params[] = $categoryId;
        }
        if ($search !== null) {
            $conditions[] = '(a.title_fr LIKE ? OR a.slug LIKE ?)';
            $params[] = '%' . addcslashes($search, '%_\\') . '%';
            $params[] = '%' . addcslashes($search, '%_\\') . '%';
        }

        return [implode(' AND ', $conditions), $params];
    }
}
