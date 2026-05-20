<?php
/**
 * Greyshades Innovations - Application Configuration
 * Loads .env (if present) and exposes a global config() helper.
 */

declare(strict_types=1);

// ----- minimal .env loader -----
$envPath = dirname(__DIR__) . '/.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#')) continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        $v = trim($v, "\"' ");
        if ($k !== '' && getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

function env(string $key, mixed $default = null): mixed {
    $val = $_ENV[$key] ?? getenv($key);
    if ($val === false || $val === null) return $default;
    $low = strtolower((string)$val);
    return match ($low) {
        'true'  => true,
        'false' => false,
        'null'  => null,
        default => $val,
    };
}

return [
    'app' => [
        'name'    => env('APP_NAME', 'Greyshades Media Platform'),
        'env'     => env('APP_ENV', 'production'),
        'debug'   => (bool) env('APP_DEBUG', false),
        'url'     => env('APP_URL', ''),
        'key'     => env('APP_KEY', 'change-me'),
        'tz'      => 'Asia/Kolkata',
    ],
    'db' => [
        'host' => env('DB_HOST', 'localhost'),
        'port' => (int) env('DB_PORT', 3306),
        'name' => env('DB_NAME', 'greyshades_media'),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],
    'storage' => [
        'path'           => env('STORAGE_PATH', dirname(__DIR__) . '/storage'),
        'upload_max_mb'  => (int) env('UPLOAD_MAX_MB', 2048),
        'originals'      => '/uploads/originals',
        'thumbnails'     => '/uploads/thumbnails',
        'pdf_previews'   => '/uploads/pdf-previews',
        'ppt_previews'   => '/uploads/ppt-previews',
        'hls'            => '/uploads/hls',
    ],
    'media' => [
        'ffmpeg'      => env('FFMPEG_BIN', '/usr/bin/ffmpeg'),
        'ffprobe'     => env('FFPROBE_BIN', '/usr/bin/ffprobe'),
        'imagemagick' => env('IMAGEMAGICK_BIN', '/usr/bin/convert'),
        'libreoffice' => env('LIBREOFFICE_BIN', '/usr/bin/libreoffice'),
        'allowed_mimes' => [
            'video/mp4',
            'image/png', 'image/jpeg', 'image/webp', 'image/gif',
            'application/pdf',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ],
        'hls_renditions' => [
            ['name' => '240p',  'width' => 426,  'height' => 240,  'vbitrate' => '400k',  'abitrate' => '64k'],
            ['name' => '480p',  'width' => 854,  'height' => 480,  'vbitrate' => '1000k', 'abitrate' => '96k'],
            ['name' => '720p',  'width' => 1280, 'height' => 720,  'vbitrate' => '2500k', 'abitrate' => '128k'],
            ['name' => '1080p', 'width' => 1920, 'height' => 1080, 'vbitrate' => '5000k', 'abitrate' => '192k'],
        ],
    ],
    'security' => [
        'session_lifetime_min' => (int) env('SESSION_LIFETIME_MIN', 120),
        'stream_token_ttl_min' => (int) env('STREAM_TOKEN_TTL_MIN', 15),
        'enable_watermark'     => (bool) env('ENABLE_WATERMARK', true),
        'enable_anti_inspect'  => (bool) env('ENABLE_ANTI_INSPECT', true),
    ],
    'sections' => [
        'graphics' => 'Greyshades Graphics',
        'events'   => 'Greyshades Events',
    ],
];
