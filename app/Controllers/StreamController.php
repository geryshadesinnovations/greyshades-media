<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Database;
use App\Core\StreamToken;
use App\Models\Media;
use App\Models\Section;

/**
 * Token-protected media streaming.
 *
 * Real file paths NEVER appear in URLs. Every byte that reaches the browser
 * is gated by a short-lived, user+media-bound token plus a session check.
 *
 * Routes:
 *   GET /stream/{uuid}?token=...           -> original file (HTTP range)
 *   GET /stream/{uuid}/hls/{seg}?token=... -> HLS master / variant playlist / segment
 *   GET /thumb/{uuid}                      -> tiny thumbnail (allowed if user can see media)
 *   GET /preview/{uuid}                    -> pdf/ppt preview image
 *   GET /download/{uuid}                   -> permission-gated download (logs everything)
 */
final class StreamController
{
    /** @var array<string,string> */
    private const HLS_MIME = [
        'm3u8' => 'application/vnd.apple.mpegurl',
        'ts'   => 'video/mp2t',
    ];

    public function stream(string $uuid): void
    {
        $m = $this->authorize($uuid, requireToken: true);
        if (!$m) return;
        $abs = storage_path((string) $m['file_path']);
        if (!is_file($abs)) { http_response_code(404); return; }
        $this->serveFile($abs, (string) $m['mime_type']);
    }

    public function hls(string $uuid, string $seg): void
    {
        $m = $this->authorize($uuid, requireToken: true);
        if (!$m) return;
        if (empty($m['hls_master'])) { http_response_code(404); return; }

        // segment file must be inside the HLS dir for this media
        $hlsDir = dirname(storage_path((string) $m['hls_master']));
        $seg = basename($seg); // strip any path traversal
        $abs = $hlsDir . '/' . $seg;
        if (!is_file($abs) || !str_starts_with(realpath($abs) ?: '', realpath($hlsDir) ?: '')) {
            http_response_code(404); return;
        }
        $ext = strtolower((string) pathinfo($abs, PATHINFO_EXTENSION));
        $mime = self::HLS_MIME[$ext] ?? 'application/octet-stream';

        // For .m3u8 playlists we MUST rewrite each relative reference to include
        // the access token, otherwise the browser fetches them as anonymous and
        // the StreamController correctly returns 403. We do that here on the fly
        // so the original playlist files on disk stay untouched.
        if ($ext === 'm3u8') {
            $token = (string) ($_GET['token'] ?? '');
            $contents = (string) file_get_contents($abs);
            $rewritten = $this->rewriteHlsPlaylist($contents, (string) $m['uuid'], $token);
            header('X-Content-Type-Options: nosniff');
            header('Content-Type: ' . $mime);
            header('Cache-Control: private, no-store');
            header('Content-Length: ' . strlen($rewritten));
            echo $rewritten;
            return;
        }

        $this->serveFile($abs, $mime, allowRange: false);
    }

    /**
     * Rewrite an HLS playlist so every variant/segment URI carries the access
     * token and points back at our /stream/{uuid}/hls/{seg} endpoint.
     */
    private function rewriteHlsPlaylist(string $contents, string $uuid, string $token): string
    {
        $base = url('/stream/' . $uuid . '/hls/');
        $tokenSuffix = '?token=' . rawurlencode($token);
        $out = [];
        foreach (preg_split("/\r?\n/", $contents) as $line) {
            $trim = trim($line);
            if ($trim === '' || $trim[0] === '#') {
                $out[] = $line;
                continue;
            }
            // Skip absolute URIs (shouldn't appear, but defensive)
            if (preg_match('#^https?://#i', $trim)) {
                $out[] = $line;
                continue;
            }
            $out[] = $base . $trim . $tokenSuffix;
        }
        return implode("\n", $out);
    }

    public function thumb(string $uuid): void
    {
        $m = $this->authorize($uuid, requireToken: false);
        if (!$m) return;
        $rel = $m['thumbnail_path'] ?: $m['preview_path'];
        if (!$rel) {
            // fallback to a tiny SVG placeholder
            header('Content-Type: image/svg+xml');
            echo '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="400"><rect width="100%" height="100%" fill="#1f2937"/><text x="50%" y="50%" fill="#9ca3af" font-family="sans-serif" text-anchor="middle" dy=".3em">No preview</text></svg>';
            return;
        }
        $abs = storage_path((string) $rel);
        if (!is_file($abs)) { http_response_code(404); return; }
        $mime = mime_content_type($abs) ?: 'image/jpeg';
        header('Cache-Control: private, max-age=3600');
        $this->serveFile($abs, $mime, allowRange: false);
    }

    public function preview(string $uuid): void
    {
        $m = $this->authorize($uuid, requireToken: false);
        if (!$m) return;
        $rel = $m['preview_path'] ?: $m['thumbnail_path'];
        if (!$rel) { http_response_code(404); return; }
        $abs = storage_path((string) $rel);
        if (!is_file($abs)) { http_response_code(404); return; }
        // Pick the right MIME from the file extension - PPT previews are now
        // PDFs (full slide deck), older uploads may still be PNGs.
        $ext = strtolower((string) pathinfo($abs, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'pdf'  => 'application/pdf',
            'png'  => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => mime_content_type($abs) ?: 'application/octet-stream',
        };
        $this->serveFile($abs, $mime, allowRange: ($mime === 'application/pdf'));
    }

    public function download(string $uuid): void
    {
        $m = $this->authorize($uuid, requireToken: false);
        if (!$m) return;

        if (!$this->isDownloadable($m)) {
            http_response_code(403); echo 'Download not permitted.'; return;
        }
        $abs = storage_path((string) $m['file_path']);
        if (!is_file($abs)) { http_response_code(404); return; }

        Database::execute(
            "INSERT INTO download_logs (media_id, user_id, ip_address, user_agent, session_id, bytes_sent)
             VALUES (?,?,?,?,?,?)",
            [$m['id'], Auth::id(), client_ip(), substr(ua(), 0, 500), session_id(), filesize($abs)]
        );
        Media::bumpDownload((int) $m['id']);
        ActivityLog::record('media.download', 'media', (int) $m['id']);

        $filename = preg_replace('~[^A-Za-z0-9._-]+~', '_', (string) $m['title'])
                  . '.' . pathinfo($abs, PATHINFO_EXTENSION);
        header('Content-Description: File Transfer');
        header('Content-Type: ' . ((string) $m['mime_type']));
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($abs));
        header('Cache-Control: private, no-store');
        readfile($abs);
        exit;
    }

    /** Returns the media row if allowed, else writes 401/403/404 and returns null. */
    private function authorize(string $uuid, bool $requireToken): ?array
    {
        if (!Auth::check()) { http_response_code(401); return null; }

        $m = Media::findByUuid($uuid);
        if (!$m) { http_response_code(404); return null; }

        $section = Section::find((int) $m['section_id']);
        if (!$section || !Auth::canSection((string) $section['code'])) {
            http_response_code(403); return null;
        }

        if ($requireToken) {
            $token = (string) ($_GET['token'] ?? '');
            if ($token === '' || !StreamToken::validate($token, (int) $m['id'])) {
                http_response_code(403); return null;
            }
        }
        return $m;
    }

    private function isDownloadable(array $m): bool
    {
        if (Auth::isSuperAdmin()) return true;
        if (!Auth::canDownload()) {
            $granted = Database::scalar(
                "SELECT 1 FROM media_download_grants
                 WHERE media_id = ? AND user_id = ?
                   AND (expires_at IS NULL OR expires_at > NOW())",
                [$m['id'], Auth::id()]
            );
            if (!$granted) return false;
        }
        if (!$m['is_downloadable']) return false;
        if (!empty($m['download_expiry']) && strtotime((string) $m['download_expiry']) < time()) return false;
        return true;
    }

    /** Stream a file with HTTP Range support (videos can seek). */
    private function serveFile(string $abs, string $mime, bool $allowRange = true): void
    {
        $size = filesize($abs);
        header('X-Content-Type-Options: nosniff');
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline');
        header('X-Frame-Options: SAMEORIGIN');
        header('Cache-Control: private, no-store');
        header('Accept-Ranges: ' . ($allowRange ? 'bytes' : 'none'));

        if ($allowRange && !empty($_SERVER['HTTP_RANGE'])) {
            if (preg_match('/bytes=(\d*)-(\d*)/', (string) $_SERVER['HTTP_RANGE'], $r)) {
                $start = $r[1] === '' ? 0 : (int) $r[1];
                $end   = $r[2] === '' ? $size - 1 : (int) $r[2];
                if ($start > $end || $end >= $size) {
                    header("Content-Range: bytes */$size");
                    http_response_code(416); return;
                }
                $length = $end - $start + 1;
                http_response_code(206);
                header("Content-Range: bytes $start-$end/$size");
                header('Content-Length: ' . $length);
                $fp = fopen($abs, 'rb');
                if (!$fp) { http_response_code(500); return; }
                fseek($fp, $start);
                $bufSize = 8192;
                while (!feof($fp) && $length > 0 && !connection_aborted()) {
                    $chunk = fread($fp, min($bufSize, $length));
                    if ($chunk === false) break;
                    echo $chunk;
                    $length -= strlen($chunk);
                    flush();
                }
                fclose($fp);
                exit;
            }
        }

        header('Content-Length: ' . $size);
        readfile($abs);
        exit;
    }
}
