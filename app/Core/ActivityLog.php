<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Centralised activity / audit logging.
 * Failures are swallowed so logging never breaks user requests.
 */
final class ActivityLog
{
    public static function record(
        string $action,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $meta = null
    ): void {
        try {
            Database::execute(
                "INSERT INTO activity_logs
                 (user_id, action, entity_type, entity_id, meta, ip_address, user_agent, session_id)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    Auth::id(),
                    $action,
                    $entityType,
                    $entityId,
                    $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
                    client_ip(),
                    substr(ua(), 0, 500),
                    session_id() ?: null,
                ]
            );
        } catch (\Throwable $e) {
            error_log('[ActivityLog] ' . $e->getMessage());
        }
    }

    public static function error(string $action, array $meta = []): void
    {
        self::record($action, 'error', null, $meta);
    }
}
