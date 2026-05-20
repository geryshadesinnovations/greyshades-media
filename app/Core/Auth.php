<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session-based authentication + per-user permission flags.
 */
final class Auth
{
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return isset($_SESSION['user']['id']) ? (int) $_SESSION['user']['id'] : null;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function attempt(string $email, string $password): bool
    {
        $row = Database::first(
            "SELECT u.*, r.code AS role_code FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE u.email = ? AND u.is_active = 1 LIMIT 1",
            [$email]
        );

        if (!$row || !password_verify($password, (string) $row['password_hash'])) {
            Database::execute(
                "INSERT INTO failed_logins (email, ip_address) VALUES (?, ?)",
                [$email, client_ip()]
            );
            return false;
        }

        // Rehash if needed
        if (password_needs_rehash($row['password_hash'], PASSWORD_BCRYPT)) {
            Database::execute(
                "UPDATE users SET password_hash = ? WHERE id = ?",
                [password_hash($password, PASSWORD_BCRYPT), $row['id']]
            );
        }

        // Regenerate session ID to prevent fixation
        session_regenerate_id(true);

        unset($row['password_hash']);
        $_SESSION['user'] = $row;

        Database::execute(
            "UPDATE users SET last_login_at = NOW(), last_login_ip = ? WHERE id = ?",
            [client_ip(), $row['id']]
        );

        // Track session
        Database::execute(
            "INSERT INTO user_sessions (id, user_id, ip_address, user_agent, last_activity_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE last_activity_at = NOW(), is_active = 1",
            [session_id(), $row['id'], client_ip(), substr(ua(), 0, 500)]
        );

        ActivityLog::record('login', null, null, ['email' => $email]);
        return true;
    }

    public static function logout(): void
    {
        if (self::check()) {
            ActivityLog::record('logout');
            Database::execute(
                "UPDATE user_sessions SET is_active = 0 WHERE id = ?",
                [session_id()]
            );
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
    }

    /** Require login or redirect to login page. */
    public static function requireLogin(): void
    {
        if (!self::check()) redirect('/login');
        // Update session activity heartbeat
        Database::execute(
            "UPDATE user_sessions SET last_activity_at = NOW() WHERE id = ?",
            [session_id()]
        );
    }

    public static function isSuperAdmin(): bool
    {
        return (self::user()['role_code'] ?? '') === 'super_admin';
    }

    /** Boolean flag from users row, e.g. 'can_upload', 'can_graphics' */
    public static function flag(string $col): bool
    {
        $u = self::user();
        return (bool) ($u[$col] ?? 0);
    }

    public static function canSection(string $sectionCode): bool
    {
        if (self::isSuperAdmin()) return true;
        return $sectionCode === 'graphics' ? self::flag('can_graphics') : self::flag('can_events');
    }

    public static function canUpload(): bool   { return self::isSuperAdmin() || self::flag('can_upload'); }
    public static function canEdit(): bool     { return self::isSuperAdmin() || self::flag('can_edit'); }
    public static function canDelete(): bool   { return self::isSuperAdmin() || self::flag('can_delete'); }
    public static function canDownload(): bool { return self::isSuperAdmin() || self::flag('can_download'); }
    public static function canManageUsers(): bool { return self::isSuperAdmin() || self::flag('can_manage_users'); }
}
