<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Tag
{
    /** Find or create a set of tags (by name), return array of IDs. */
    public static function findOrCreateMany(array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $name = trim((string) $name);
            if ($name === '') continue;
            $slug = slugify($name);
            $existing = Database::scalar("SELECT id FROM tags WHERE slug = ?", [$slug]);
            if ($existing) {
                $ids[] = (int) $existing;
                continue;
            }
            Database::execute("INSERT INTO tags (name, slug) VALUES (?,?)", [$name, $slug]);
            $ids[] = Database::lastId();
        }
        return $ids;
    }

    public static function popular(int $limit = 30): array
    {
        return Database::all(
            "SELECT t.id, t.name, t.slug, COUNT(mt.media_id) AS uses
             FROM tags t LEFT JOIN media_tags mt ON mt.tag_id = t.id
             GROUP BY t.id ORDER BY uses DESC, t.name LIMIT " . $limit
        );
    }
}
