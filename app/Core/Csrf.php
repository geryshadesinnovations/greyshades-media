<?php
declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['__csrf'])) {
            $_SESSION['__csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['__csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::token()) . '">';
    }

    public static function check(?string $token): bool
    {
        $expected = $_SESSION['__csrf'] ?? '';
        return $expected !== '' && is_string($token) && hash_equals($expected, $token);
    }

    public static function verifyOrFail(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (!in_array($method, ['POST','PUT','PATCH','DELETE'], true)) return;

        $tok = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!self::check(is_string($tok) ? $tok : null)) {
            http_response_code(419);
            echo 'CSRF token mismatch';
            exit;
        }
    }
}
