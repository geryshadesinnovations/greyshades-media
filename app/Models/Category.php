<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Category
{
    /** All categories of a section, flat. */
    public static function bySection(int $sectionId): array
    {
        return Database::all(
            "SELECT * FROM categories WHERE section_id = ? ORDER BY parent_id IS NULL DESC, parent_id, sort_order, name",
            [$sectionId]
        );
    }

    /** Build a nested tree (recursive). */
    public static function tree(int $sectionId): array
    {
        $rows = self::bySection($sectionId);
        $byParent = [];
        foreach ($rows as $r) {
            $byParent[$r['parent_id'] ?? 0][] = $r;
        }
        $build = function ($parentId) use (&$build, &$byParent): array {
            $out = [];
            foreach ($byParent[$parentId] ?? [] as $node) {
                $node['children'] = $build((int) $node['id']);
                $out[] = $node;
            }
            return $out;
        };
        return $build(0);
    }

    public static function find(int $id): ?array
    {
        return Database::first("SELECT * FROM categories WHERE id = ?", [$id]);
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::first("SELECT * FROM categories WHERE slug = ? LIMIT 1", [$slug]);
    }

    /** All descendant IDs (inclusive), used for filtering when user picks a parent. */
    public static function descendantIds(int $rootId): array
    {
        $sectionId = (int) Database::scalar("SELECT section_id FROM categories WHERE id = ?", [$rootId]);
        if (!$sectionId) return [$rootId];
        $rows = self::bySection($sectionId);
        $byParent = [];
        foreach ($rows as $r) $byParent[(int)($r['parent_id'] ?? 0)][] = (int)$r['id'];

        $out = [$rootId];
        $queue = [$rootId];
        while ($queue) {
            $cur = array_pop($queue);
            foreach ($byParent[$cur] ?? [] as $child) {
                $out[] = $child;
                $queue[] = $child;
            }
        }
        return $out;
    }

    /**
     * All ancestor IDs of $id (inclusive of $id itself), walking up via
     * parent_id. Used by the upload flow so that picking a leaf subcategory
     * automatically attaches the entire chain (root + intermediate parents),
     * which means dashboard filters by either leaf or root return the file.
     *
     * Returned order: leaf -> root.
     */
    public static function ancestorIds(int $id): array
    {
        $out = [];
        $cur = $id;
        $seen = [];
        // Hard cap to defend against any accidental cycle in the tree.
        for ($i = 0; $i < 32 && $cur > 0; $i++) {
            if (isset($seen[$cur])) break;
            $seen[$cur] = true;
            $row = Database::first("SELECT id, parent_id FROM categories WHERE id = ?", [$cur]);
            if (!$row) break;
            $out[] = (int) $row['id'];
            $cur = $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
        }
        return $out;
    }

    public static function create(int $sectionId, ?int $parentId, string $name): int
    {
        $slug = slugify($name);
        // ensure unique within section
        $base = $slug; $i = 1;
        while (Database::scalar("SELECT 1 FROM categories WHERE section_id = ? AND slug = ?", [$sectionId, $slug])) {
            $slug = $base . '-' . (++$i);
        }
        Database::execute(
            "INSERT INTO categories (section_id, parent_id, name, slug) VALUES (?,?,?,?)",
            [$sectionId, $parentId, $name, $slug]
        );
        return Database::lastId();
    }

    public static function delete(int $id): void
    {
        // children become root (ON DELETE SET NULL via FK)
        Database::execute("DELETE FROM categories WHERE id = ?", [$id]);
    }
}
