<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Media access layer.
 *
 *   - One row in `media` per physical file (UUID + sha256 hash) - never duplicated.
 *   - Categorisation, occasions and tags are pure metadata via junction tables,
 *     so the same file can show up in many places without being copied.
 */
final class Media
{
    public static function find(int $id): ?array
    {
        return Database::first("SELECT * FROM media WHERE id = ?", [$id]);
    }

    public static function findByUuid(string $uuid): ?array
    {
        return Database::first("SELECT * FROM media WHERE uuid = ?", [$uuid]);
    }

    public static function findByHash(string $hash): ?array
    {
        return Database::first("SELECT * FROM media WHERE file_hash = ?", [$hash]);
    }

    public static function create(array $data): int
    {
        Database::execute(
            "INSERT INTO media
             (uuid, section_id, title, description, keywords, media_type, mime_type,
              file_path, file_size, file_hash, thumbnail_path, preview_path,
              hls_master, duration_sec, width, height,
              is_downloadable, uploaded_by, processing_status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $data['uuid'], (int) $data['section_id'], $data['title'],
                $data['description'] ?? null, $data['keywords'] ?? null,
                $data['media_type'], $data['mime_type'],
                $data['file_path'], (int) ($data['file_size'] ?? 0),
                $data['file_hash'] ?? null,
                $data['thumbnail_path'] ?? null,
                $data['preview_path'] ?? null,
                $data['hls_master'] ?? null,
                $data['duration_sec'] ?? null,
                $data['width'] ?? null, $data['height'] ?? null,
                (int) ($data['is_downloadable'] ?? 0),
                (int) $data['uploaded_by'],
                $data['processing_status'] ?? 'ready',
            ]
        );
        return Database::lastId();
    }

    public static function attachCategories(int $mediaId, array $categoryIds): void
    {
        Database::execute("DELETE FROM media_categories WHERE media_id = ?", [$mediaId]);
        foreach (array_unique(array_map('intval', $categoryIds)) as $cid) {
            if ($cid <= 0) continue;
            Database::execute(
                "INSERT IGNORE INTO media_categories (media_id, category_id) VALUES (?,?)",
                [$mediaId, $cid]
            );
        }
    }

    public static function attachOccasions(int $mediaId, array $occasionIds): void
    {
        Database::execute("DELETE FROM media_occasions WHERE media_id = ?", [$mediaId]);
        foreach (array_unique(array_map('intval', $occasionIds)) as $oid) {
            if ($oid <= 0) continue;
            Database::execute(
                "INSERT IGNORE INTO media_occasions (media_id, occasion_id) VALUES (?,?)",
                [$mediaId, $oid]
            );
        }
    }

    public static function attachTags(int $mediaId, array $tagIds): void
    {
        Database::execute("DELETE FROM media_tags WHERE media_id = ?", [$mediaId]);
        foreach (array_unique(array_map('intval', $tagIds)) as $tid) {
            if ($tid <= 0) continue;
            Database::execute(
                "INSERT IGNORE INTO media_tags (media_id, tag_id) VALUES (?,?)",
                [$mediaId, $tid]
            );
        }
    }

    public static function categoriesFor(int $mediaId): array
    {
        return Database::all(
            "SELECT c.* FROM media_categories mc
             JOIN categories c ON c.id = mc.category_id
             WHERE mc.media_id = ? ORDER BY c.name",
            [$mediaId]
        );
    }

    public static function occasionsFor(int $mediaId): array
    {
        return Database::all(
            "SELECT o.*, g.name AS group_name FROM media_occasions mo
             JOIN occasions o ON o.id = mo.occasion_id
             JOIN occasion_groups g ON g.id = o.group_id
             WHERE mo.media_id = ? ORDER BY o.name",
            [$mediaId]
        );
    }

    public static function tagsFor(int $mediaId): array
    {
        return Database::all(
            "SELECT t.* FROM media_tags mt JOIN tags t ON t.id = mt.tag_id
             WHERE mt.media_id = ? ORDER BY t.name",
            [$mediaId]
        );
    }

    public static function update(int $id, array $data): void
    {
        $allowed = ['title','description','keywords','is_downloadable','is_featured','is_pinned','download_expiry'];
        $sets = []; $params = [];
        foreach ($allowed as $k) {
            if (array_key_exists($k, $data)) {
                $sets[] = "$k = ?";
                $params[] = is_bool($data[$k]) ? (int) $data[$k] : $data[$k];
            }
        }
        if (!$sets) return;
        $params[] = $id;
        Database::execute("UPDATE media SET " . implode(',', $sets) . " WHERE id = ?", $params);
    }

    public static function delete(int $id): void
    {
        Database::execute("DELETE FROM media WHERE id = ?", [$id]);
    }

    public static function bumpView(int $id): void
    {
        Database::execute("UPDATE media SET view_count = view_count + 1 WHERE id = ?", [$id]);
    }

    public static function bumpDownload(int $id): void
    {
        Database::execute("UPDATE media SET download_count = download_count + 1 WHERE id = ?", [$id]);
    }

    /**
     * Powerful search/list:
     *   filters:
     *     section_code   string (graphics|events)
     *     category_id    int    (matches that category OR any of its descendants)
     *     occasion_id    int
     *     tag_id         int
     *     media_type     string
     *     q              string (FULLTEXT or LIKE fallback)
     *     uploader_id    int
     *     featured       bool
     *   pagination:
     *     page, per_page (default 24)
     *   sort:
     *     newest|oldest|popular|az
     *   permissions:
     *     allowedSections: ['graphics','events']  (empty -> see nothing)
     */
    public static function search(array $filters, array $allowedSections, string $sort = 'newest', int $page = 1, int $perPage = 24): array
    {
        if (!$allowedSections) return ['rows' => [], 'total' => 0, 'page' => 1, 'per_page' => $perPage, 'pages' => 0];

        $where = [];
        $params = [];

        // section visibility
        $secMarks = implode(',', array_fill(0, count($allowedSections), '?'));
        $where[] = "s.code IN ($secMarks)";
        foreach ($allowedSections as $sc) $params[] = $sc;

        if (!empty($filters['section_code']) && empty($filters['category_id'])) {
            if (in_array($filters['section_code'], $allowedSections, true)) {
                $where[] = "s.code = ?";
                $params[] = $filters['section_code'];
            }
        }

        $joinCat = '';
        if (!empty($filters['category_id'])) {
            $ids = Category::descendantIds((int) $filters['category_id']);
            $marks = implode(',', array_fill(0, count($ids), '?'));
            $joinCat = "INNER JOIN media_categories mc ON mc.media_id = m.id AND mc.category_id IN ($marks)";
            foreach ($ids as $cid) $params[] = $cid;
        }

        $joinOcc = '';
        if (!empty($filters['occasion_id'])) {
            $joinOcc = "INNER JOIN media_occasions mo ON mo.media_id = m.id AND mo.occasion_id = ?";
            $params[] = (int) $filters['occasion_id'];
        }

        $joinTag = '';
        if (!empty($filters['tag_id'])) {
            $joinTag = "INNER JOIN media_tags mt ON mt.media_id = m.id AND mt.tag_id = ?";
            $params[] = (int) $filters['tag_id'];
        }

        if (!empty($filters['media_type'])) {
            $where[] = "m.media_type = ?";
            $params[] = $filters['media_type'];
        }
        if (!empty($filters['uploader_id'])) {
            $where[] = "m.uploaded_by = ?";
            $params[] = (int) $filters['uploader_id'];
        }
        if (!empty($filters['featured'])) {
            $where[] = "m.is_featured = 1";
        }
        if (!empty($filters['q'])) {
            $q = trim((string) $filters['q']);
            $like = '%' . $q . '%';
            $where[] = "(m.title LIKE ? OR m.description LIKE ? OR m.keywords LIKE ?)";
            $params[] = $like; $params[] = $like; $params[] = $like;
        }

        $orderBy = match ($sort) {
            'oldest'  => 'm.created_at ASC',
            'popular' => 'm.view_count DESC, m.created_at DESC',
            'az'      => 'm.title ASC',
            default   => 'm.is_pinned DESC, m.is_featured DESC, m.created_at DESC',
        };

        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        $base = "FROM media m
                 INNER JOIN sections s ON s.id = m.section_id
                 $joinCat $joinOcc $joinTag
                 WHERE " . implode(' AND ', $where);

        $total = (int) Database::scalar("SELECT COUNT(DISTINCT m.id) $base", $params);

        $rows = Database::all(
            "SELECT DISTINCT m.*, s.code AS section_code, s.name AS section_name
             $base
             ORDER BY $orderBy
             LIMIT $perPage OFFSET $offset",
            $params
        );

        return [
            'rows'     => $rows,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => (int) ceil($total / $perPage),
        ];
    }

    /** Stats for admin dashboard */
    public static function statsOverview(): array
    {
        return [
            'media_total'    => (int) Database::scalar("SELECT COUNT(*) FROM media"),
            'media_video'    => (int) Database::scalar("SELECT COUNT(*) FROM media WHERE media_type='video'"),
            'media_image'    => (int) Database::scalar("SELECT COUNT(*) FROM media WHERE media_type='image'"),
            'media_pdf'      => (int) Database::scalar("SELECT COUNT(*) FROM media WHERE media_type='pdf'"),
            'media_ppt'      => (int) Database::scalar("SELECT COUNT(*) FROM media WHERE media_type='ppt'"),
            'storage_bytes'  => (int) Database::scalar("SELECT COALESCE(SUM(file_size),0) FROM media"),
            'users_total'    => (int) Database::scalar("SELECT COUNT(*) FROM users WHERE is_active = 1"),
            'sessions_live'  => (int) Database::scalar("SELECT COUNT(*) FROM user_sessions WHERE is_active = 1 AND last_activity_at > (NOW() - INTERVAL 15 MINUTE)"),
            'downloads_30d'  => (int) Database::scalar("SELECT COUNT(*) FROM download_logs WHERE created_at > (NOW() - INTERVAL 30 DAY)"),
            'views_30d'      => (int) Database::scalar("SELECT COUNT(*) FROM view_logs WHERE created_at > (NOW() - INTERVAL 30 DAY)"),
        ];
    }

    public static function topViewed(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        return Database::all(
            "SELECT id, title, media_type, view_count FROM media ORDER BY view_count DESC LIMIT $limit"
        );
    }

    public static function topDownloaded(int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        return Database::all(
            "SELECT id, title, media_type, download_count FROM media ORDER BY download_count DESC LIMIT $limit"
        );
    }
}
