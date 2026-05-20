<?php
/**
 * Greyshades Innovations - Application Bootstrap
 * Wires up autoloading, config, sessions, error handling.
 */
declare(strict_types=1);

// ------- Config -------
$GLOBALS['__config'] = require BASE_PATH . '/config/config.php';

date_default_timezone_set($GLOBALS['__config']['app']['tz'] ?? 'UTC');

// ------- Autoloader (PSR-4 lite) -------
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $rel = substr($class, strlen($prefix));
    $path = APP_PATH . '/' . str_replace('\\', '/', $rel) . '.php';
    if (is_file($path)) require $path;
});

// ------- Helpers -------
require APP_PATH . '/Core/helpers.php';

// ------- Error handling -------
$debug = (bool) ($GLOBALS['__config']['app']['debug'] ?? false);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
error_reporting(E_ALL);

set_exception_handler(function (Throwable $e) use ($debug): void {
    @\App\Core\ActivityLog::error('exception', [
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
    http_response_code(500);
    if ($debug) {
        echo '<pre style="font:12px monospace;padding:24px;background:#111;color:#f55;">';
        echo htmlspecialchars((string)$e);
        echo '</pre>';
    } else {
        echo '<h1>Internal Server Error</h1><p>Please contact your administrator.</p>';
    }
    exit;
});

// ------- Secure session -------
$cfg = $GLOBALS['__config'];
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => '',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.gc_maxlifetime', (string) ($cfg['security']['session_lifetime_min'] * 60));
session_name('GREYSHADES_SID');
session_start();

// ------- Security headers -------
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), camera=(), microphone=()');
// CSP: allow inline for our hashed scripts; in production tighten further.
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; "
     . "media-src 'self' blob:; style-src 'self' 'unsafe-inline'; "
     . "script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; "
     . "font-src 'self' data: https://cdn.jsdelivr.net; "
     . "connect-src 'self' https://cdn.jsdelivr.net; frame-ancestors 'self'");
