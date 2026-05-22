<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Media processing service.
 *
 * Wraps FFmpeg / ImageMagick / LibreOffice CLI tools to generate:
 *  - video thumbnails + duration metadata
 *  - HLS adaptive streaming bundles (multiple renditions + master.m3u8)
 *  - PDF first-page preview images
 *  - PPT/PPTX first-slide preview images (via libreoffice -> pdf -> imagick)
 *  - optimised image previews
 *
 * All methods degrade gracefully if the underlying binary is missing -
 * the upload still succeeds, just without an auto preview. This is
 * important so the platform is usable even before media tooling is
 * provisioned on the server.
 */
final class MediaProcessor
{
    public static function isVideo(string $mime): bool { return str_starts_with($mime, 'video/'); }
    public static function isImage(string $mime): bool { return str_starts_with($mime, 'image/'); }
    public static function isPdf(string $mime): bool   { return $mime === 'application/pdf'; }
    public static function isPpt(string $mime): bool   {
        return in_array($mime, [
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ], true);
    }

    public static function classify(string $mime): string
    {
        if (self::isVideo($mime)) return 'video';
        if (self::isImage($mime)) return 'image';
        if (self::isPdf($mime))   return 'pdf';
        if (self::isPpt($mime))   return 'ppt';
        return 'other';
    }

    /** Generate a thumbnail for a video at ~1 second; returns relative path or null. */
    public static function videoThumbnail(string $absVideo, string $absThumbOut): ?string
    {
        $ffmpeg = (string) config('media.ffmpeg');
        if (!self::binExists($ffmpeg)) return null;
        $cmd = sprintf(
            '%s -y -ss 00:00:01 -i %s -vframes 1 -q:v 2 -vf "scale=480:-1" %s 2>&1',
            escapeshellcmd($ffmpeg),
            escapeshellarg($absVideo),
            escapeshellarg($absThumbOut)
        );
        @exec($cmd, $out, $code);
        return $code === 0 && is_file($absThumbOut) ? $absThumbOut : null;
    }

    /** Probe duration in seconds, or null. */
    public static function videoDuration(string $absVideo): ?int
    {
        $ffprobe = (string) config('media.ffprobe');
        if (!self::binExists($ffprobe)) return null;
        $cmd = sprintf(
            '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s',
            escapeshellcmd($ffprobe),
            escapeshellarg($absVideo)
        );
        $out = @shell_exec($cmd);
        if (!$out) return null;
        return (int) round((float) trim($out));
    }

    /**
     * Convert a video to multi-resolution HLS (master.m3u8).
     * Returns absolute path to master playlist or null.
     *
     * NOTE: this is CPU-heavy. Production should run this via a job queue;
     * Phase 1 calls it synchronously (small uploads) and falls back to
     * direct MP4 streaming if FFmpeg is unavailable.
     */
    public static function transcodeHls(string $absVideo, string $outDir): ?string
    {
        $ffmpeg = (string) config('media.ffmpeg');
        if (!self::binExists($ffmpeg)) return null;

        if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) return null;

        $renditions = (array) config('media.hls_renditions', []);
        $masterEntries = ["#EXTM3U", "#EXT-X-VERSION:3"];
        foreach ($renditions as $r) {
            $name = $r['name'];
            $playlist = $outDir . "/{$name}.m3u8";
            $segPattern = $outDir . "/{$name}_%03d.ts";
            $cmd = sprintf(
                '%s -y -i %s -vf "scale=w=%d:h=%d:force_original_aspect_ratio=decrease" '
                . '-c:a aac -ar 48000 -b:a %s -c:v h264 -profile:v main -crf 23 -g 48 -keyint_min 48 '
                . '-sc_threshold 0 -b:v %s -maxrate %s -bufsize %s -hls_time 4 -hls_playlist_type vod '
                . '-hls_segment_filename %s %s 2>&1',
                escapeshellcmd($ffmpeg),
                escapeshellarg($absVideo),
                $r['width'], $r['height'],
                $r['abitrate'],
                $r['vbitrate'], $r['vbitrate'], $r['vbitrate'],
                escapeshellarg($segPattern),
                escapeshellarg($playlist)
            );
            @exec($cmd, $o, $code);
            if ($code !== 0) continue;

            $bandwidth = (int) (((int) rtrim($r['vbitrate'], 'k')) * 1000);
            $masterEntries[] = "#EXT-X-STREAM-INF:BANDWIDTH={$bandwidth},RESOLUTION={$r['width']}x{$r['height']},NAME=\"{$name}\"";
            $masterEntries[] = "{$name}.m3u8";
        }
        $master = $outDir . '/master.m3u8';
        file_put_contents($master, implode("\n", $masterEntries) . "\n");
        return $master;
    }

    /** Render first PDF page to PNG; returns path or null. */
    public static function pdfPreview(string $absPdf, string $absPngOut): ?string
    {
        $convert = (string) config('media.imagemagick');
        if (!self::binExists($convert)) return null;
        $cmd = sprintf(
            '%s -density 150 %s[0] -quality 85 -resize 800x %s 2>&1',
            escapeshellcmd($convert),
            escapeshellarg($absPdf),
            escapeshellarg($absPngOut)
        );
        @exec($cmd, $out, $code);
        return $code === 0 && is_file($absPngOut) ? $absPngOut : null;
    }

    /** Convert PPT to PDF via LibreOffice, then take first-page preview. */
    public static function pptPreview(string $absPpt, string $absPngOut, string $tmpDir): ?string
    {
        $lo = (string) config('media.libreoffice');
        if (!self::binExists($lo)) return null;
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) return null;

        $cmd = sprintf(
            '%s --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellcmd($lo),
            escapeshellarg($tmpDir),
            escapeshellarg($absPpt)
        );
        @exec($cmd, $out, $code);
        if ($code !== 0) return null;

        $pdfPath = $tmpDir . '/' . pathinfo($absPpt, PATHINFO_FILENAME) . '.pdf';
        if (!is_file($pdfPath)) return null;
        return self::pdfPreview($pdfPath, $absPngOut);
    }

    /**
     * Convert a PPT/PPTX to a full PDF (all slides, navigable in the browser
     * PDF viewer) and ALSO render a first-slide PNG thumbnail.
     *
     * Returns ['pdf' => absPdfPath|null, 'thumb' => absPngPath|null]. Either
     * value may be null if the corresponding tool is unavailable - the
     * upload still succeeds, just without that asset.
     */
    public static function pptToPdfAndThumbnail(string $absPpt, string $absPdfOut, string $absThumbOut, string $tmpDir): array
    {
        $lo = (string) config('media.libreoffice');
        if (!self::binExists($lo)) return ['pdf' => null, 'thumb' => null];
        if (!is_dir($tmpDir) && !mkdir($tmpDir, 0775, true) && !is_dir($tmpDir)) {
            return ['pdf' => null, 'thumb' => null];
        }

        // 1. Convert PPT -> PDF in temp dir
        $cmd = sprintf(
            '%s --headless --convert-to pdf --outdir %s %s 2>&1',
            escapeshellcmd($lo),
            escapeshellarg($tmpDir),
            escapeshellarg($absPpt)
        );
        @exec($cmd, $out, $code);
        if ($code !== 0) return ['pdf' => null, 'thumb' => null];

        $pdfTmp = $tmpDir . '/' . pathinfo($absPpt, PATHINFO_FILENAME) . '.pdf';
        if (!is_file($pdfTmp)) return ['pdf' => null, 'thumb' => null];

        // 2. Move/copy the full PDF to its final location (used by /preview/)
        $pdfFinal = null;
        $outDir = dirname($absPdfOut);
        if (!is_dir($outDir)) @mkdir($outDir, 0775, true);
        if (@rename($pdfTmp, $absPdfOut) || @copy($pdfTmp, $absPdfOut)) {
            $pdfFinal = $absPdfOut;
            // If we copied, clean the tmp file
            if (is_file($pdfTmp)) @unlink($pdfTmp);
        }

        // 3. Render first-page thumbnail PNG (for grid display)
        $thumbFinal = null;
        if ($pdfFinal) {
            $thumbDir = dirname($absThumbOut);
            if (!is_dir($thumbDir)) @mkdir($thumbDir, 0775, true);
            $thumbFinal = self::pdfPreview($pdfFinal, $absThumbOut);
        }

        return ['pdf' => $pdfFinal, 'thumb' => $thumbFinal];
    }

    /** Build an optimised JPEG/WebP thumbnail for an image (max 600px). */
    public static function imageThumbnail(string $absImage, string $absThumbOut): ?string
    {
        $convert = (string) config('media.imagemagick');
        if (self::binExists($convert)) {
            $cmd = sprintf(
                '%s %s -auto-orient -strip -resize 600x600^ -quality 82 %s 2>&1',
                escapeshellcmd($convert),
                escapeshellarg($absImage),
                escapeshellarg($absThumbOut)
            );
            @exec($cmd, $out, $code);
            if ($code === 0 && is_file($absThumbOut)) return $absThumbOut;
        }
        // Fallback: just copy the original (browser will scale)
        return @copy($absImage, $absThumbOut) ? $absThumbOut : null;
    }

    private static function binExists(string $bin): bool
    {
        if ($bin === '') return false;
        if (is_file($bin) && is_executable($bin)) return true;
        // PATH lookup
        $which = @shell_exec('command -v ' . escapeshellarg($bin) . ' 2>/dev/null');
        return is_string($which) && trim($which) !== '';
    }
}
