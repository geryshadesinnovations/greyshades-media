<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Short-lived, user+media-bound tokens used by the streaming endpoint.
 * Tokens are stored in DB so they can be revoked on logout.
 */
final class StreamToken
{
    public static function issue(int $mediaId): string
    {
        $userId = Auth::id();
        if (!$userId) throw new \RuntimeException('Not authenticated');

        $ttl   = (int) config('security.stream_token_ttl_min', 15);
        $token = bin2hex(random_bytes(32));
        $expires = (new \DateTimeImmutable("+{$ttl} minutes"))->format('Y-m-d H:i:s');

        Database::execute(
            "INSERT INTO stream_tokens (token, user_id, media_id, expires_at) VALUES (?,?,?,?)",
            [$token, $userId, $mediaId, $expires]
        );
        return $token;
    }

    /** Validate token returns matching user_id+media_id row, or null. */
    public static function validate(string $token, int $mediaId): ?array
    {
        $row = Database::first(
            "SELECT * FROM stream_tokens
             WHERE token = ? AND media_id = ? AND expires_at > NOW() LIMIT 1",
            [$token, $mediaId]
        );
        return $row ?: null;
    }

    public static function revokeForUser(int $userId): void
    {
        Database::execute("DELETE FROM stream_tokens WHERE user_id = ?", [$userId]);
    }

    public static function purgeExpired(): void
    {
        Database::execute("DELETE FROM stream_tokens WHERE expires_at < NOW()");
    }
}
