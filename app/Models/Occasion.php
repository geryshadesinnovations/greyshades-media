<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Occasion
{
    /** Group occasions by their occasion_group for the filter UI. */
    public static function groupedAll(): array
    {
        $rows = Database::all(
            "SELECT o.*, g.code AS group_code, g.name AS group_name
             FROM occasions o JOIN occasion_groups g ON g.id = o.group_id
             ORDER BY g.id, o.name"
        );
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['group_code']]['name'] = $r['group_name'];
            $grouped[$r['group_code']]['items'][] = $r;
        }
        return $grouped;
    }

    public static function all(): array
    {
        return Database::all("SELECT * FROM occasions ORDER BY name");
    }

    public static function find(int $id): ?array
    {
        return Database::first("SELECT * FROM occasions WHERE id = ?", [$id]);
    }
}
