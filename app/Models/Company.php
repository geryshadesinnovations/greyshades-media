<?php
declare(strict_types=1);
namespace App\Models;
use App\Core\Database;

final class Company
{
    public static function all(): array
    {
        return Database::all("SELECT * FROM companies WHERE is_active = 1 ORDER BY name");
    }

    public static function find(int $id): ?array
    {
        return Database::first("SELECT * FROM companies WHERE id = ?", [$id]);
    }

    public static function create(string $name): int
    {
        $slug = slugify($name);
        $base = $slug; $i = 1;
        while (Database::scalar("SELECT 1 FROM companies WHERE slug = ?", [$slug])) {
            $slug = $base . '-' . (++$i);
        }
        Database::execute("INSERT INTO companies (name, slug) VALUES (?,?)", [$name, $slug]);
        return Database::lastId();
    }

    public static function update(int $id, string $name): void
    {
        Database::execute("UPDATE companies SET name = ? WHERE id = ?", [$name, $id]);
    }

    public static function delete(int $id): void
    {
        Database::execute("DELETE FROM companies WHERE id = ?", [$id]);
    }
}
