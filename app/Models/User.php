<?php
declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User
{
    public static function all(): array
    {
        return Database::all(
            "SELECT u.*, r.code AS role_code, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id
             ORDER BY u.created_at DESC"
        );
    }

    public static function find(int $id): ?array
    {
        return Database::first(
            "SELECT u.*, r.code AS role_code, r.name AS role_name
             FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ?",
            [$id]
        );
    }

    public static function roles(): array
    {
        return Database::all("SELECT * FROM roles ORDER BY id");
    }

    public static function create(array $data): int
    {
        Database::execute(
            "INSERT INTO users
             (name, email, password_hash, role_id,
              can_graphics, can_events, can_upload, can_edit, can_delete, can_download, can_manage_users, is_active)
             VALUES (?,?,?,?, ?,?,?,?,?,?,?, ?)",
            [
                $data['name'], $data['email'],
                password_hash((string) $data['password'], PASSWORD_BCRYPT),
                (int) $data['role_id'],
                (int) ($data['can_graphics']     ?? 0),
                (int) ($data['can_events']       ?? 0),
                (int) ($data['can_upload']       ?? 0),
                (int) ($data['can_edit']         ?? 0),
                (int) ($data['can_delete']       ?? 0),
                (int) ($data['can_download']     ?? 0),
                (int) ($data['can_manage_users'] ?? 0),
                (int) ($data['is_active']        ?? 1),
            ]
        );
        return Database::lastId();
    }

    public static function update(int $id, array $data): void
    {
        $sets = []; $params = [];
        foreach ([
            'name','email','role_id','can_graphics','can_events',
            'can_upload','can_edit','can_delete','can_download','can_manage_users','is_active'
        ] as $k) {
            if (array_key_exists($k, $data)) {
                $sets[] = "$k = ?";
                $params[] = is_bool($data[$k]) ? (int) $data[$k] : $data[$k];
            }
        }
        if (!empty($data['password'])) {
            $sets[] = 'password_hash = ?';
            $params[] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }
        if (!$sets) return;
        $params[] = $id;
        Database::execute("UPDATE users SET " . implode(',', $sets) . " WHERE id = ?", $params);
    }

    public static function delete(int $id): void
    {
        Database::execute("UPDATE users SET is_active = 0 WHERE id = ?", [$id]);
    }
}
