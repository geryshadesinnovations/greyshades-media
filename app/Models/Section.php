<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Section
{
    public static function all(): array
    {
        return Database::all("SELECT * FROM sections ORDER BY id");
    }

    public static function findByCode(string $code): ?array
    {
        return Database::first("SELECT * FROM sections WHERE code = ?", [$code]);
    }

    public static function find(int $id): ?array
    {
        return Database::first("SELECT * FROM sections WHERE id = ?", [$id]);
    }
}
