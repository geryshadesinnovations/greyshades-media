<?php
declare(strict_types=1);

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed {
        $cfg = $GLOBALS['__config'] ?? [];
        $parts = explode('.', $key);
        foreach ($parts as $p) {
            if (!is_array($cfg) || !array_key_exists($p, $cfg)) return $default;
            $cfg = $cfg[$p];
        }
        return $cfg;
    }
}

if (!function_exists('e')) {
    function e(?string $s): string {
        return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string {
        $base = rtrim((string) config('app.url', ''), '/');
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string {
        return url('/assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $code = 302): never {
        header('Location: ' . url($path), true, $code);
        exit;
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed {
        return $_SESSION['__old'][$key] ?? $default;
    }
}

if (!function_exists('flash')) {
    function flash(string $key, ?string $msg = null): ?string {
        if ($msg !== null) {
            $_SESSION['__flash'][$key] = $msg;
            return null;
        }
        $v = $_SESSION['__flash'][$key] ?? null;
        unset($_SESSION['__flash'][$key]);
        return $v;
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $sub = ''): string {
        $base = (string) config('storage.path', dirname(__DIR__, 2) . '/storage');
        return rtrim($base, '/') . ($sub === '' ? '' : '/' . ltrim($sub, '/'));
    }
}

if (!function_exists('slugify')) {
    function slugify(string $text): string {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text) ?? '';
        $text = trim($text, '-');
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
        $text = strtolower($text);
        $text = preg_replace('~[^-\w]+~', '', $text) ?? '';
        return $text === '' ? 'item' : $text;
    }
}

if (!function_exists('client_ip')) {
    function client_ip(): string {
        foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','REMOTE_ADDR'] as $k) {
            if (!empty($_SERVER[$k])) {
                $ip = explode(',', (string)$_SERVER[$k])[0];
                return trim($ip);
            }
        }
        return '0.0.0.0';
    }
}

if (!function_exists('ua')) {
    function ua(): string { return (string)($_SERVER['HTTP_USER_AGENT'] ?? ''); }
}

if (!function_exists('format_bytes')) {
    function format_bytes(int $bytes, int $precision = 2): string {
        $units = ['B','KB','MB','GB','TB'];
        $i = 0;
        $n = (float) $bytes;
        while ($n >= 1024 && $i < count($units) - 1) { $n /= 1024; $i++; }
        return round($n, $precision) . ' ' . $units[$i];
    }
}

if (!function_exists('format_duration')) {
    function format_duration(?int $seconds): string {
        if (!$seconds || $seconds <= 0) return '';
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;
        return $h > 0 ? sprintf('%d:%02d:%02d', $h, $m, $s) : sprintf('%d:%02d', $m, $s);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): string {
        return \App\Core\View::render($template, $data);
    }
}
