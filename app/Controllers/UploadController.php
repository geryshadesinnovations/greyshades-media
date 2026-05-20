<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Models\Category;
use App\Models\Media;
use App\Models\Occasion;
use App\Models\Section;
use App\Models\Tag;
use App\Services\MediaProcessor;

final class UploadController
{
    public function showForm(): void
    {
        if (!Auth::canUpload()) { http_response_code(403); echo view('errors/403', []); return; }

        $sections = array_values(array_filter(
            Section::all(),
            fn ($s) => Auth::canSection($s['code'])
        ));
        $trees = [];
        foreach ($sections as $s) $trees[$s['code']] = Category::tree((int) $s['id']);

        echo view('upload/form', [
            'sections'  => $sections,
            'trees'     => $trees,
            'occasions' => Occasion::groupedAll(),
            'maxMb'     => (int) config('storage.upload_max_mb', 2048),
            'allowed'   => (array) config('media.allowed_mimes', []),
        ]);
    }

    public function store(): void
    {
        Csrf::verifyOrFail();
        if (!Auth::canUpload()) { http_response_code(403); echo 'Forbidden'; return; }

        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->jsonError('No file uploaded or upload error.');
        }
        $file = $_FILES['file'];

        // Validate mime
        $mime = $this->detectMime($file['tmp_name'], (string) $file['name']);
        $allowed = (array) config('media.allowed_mimes', []);
        if (!in_array($mime, $allowed, true)) {
            $this->jsonError('File type not allowed: ' . $mime);
        }

        // Categories drive the section now. The section is automatically derived
        // from each selected category's section_id - the first one becomes the
        // primary section_id on the media row, but media_categories rows record
        // every category exactly so dashboard filters keep working.
        $catIds = array_filter(array_map('intval', (array) ($_POST['categories'] ?? [])));
        if (empty($catIds)) {
            $this->jsonError('Please select at least one category.');
        }

        // Look up section for every chosen category, validate user access.
        $marks = implode(',', array_fill(0, count($catIds), '?'));
        $catRows = Database::all(
            "SELECT c.id, c.section_id, s.code AS section_code
             FROM categories c JOIN sections s ON s.id = c.section_id
             WHERE c.id IN ($marks)",
            $catIds
        );
        if (count($catRows) !== count($catIds)) {
            $this->jsonError('One or more selected categories are invalid.');
        }
        // Filter out categories whose section the user cannot access.
        $allowedRows = array_values(array_filter(
            $catRows,
            fn ($r) => Auth::canSection((string) $r['section_code'])
        ));
        if (empty($allowedRows)) {
            $this->jsonError('You do not have permission to upload to the selected categories.');
        }
        $primarySectionId = (int) $allowedRows[0]['section_id'];
        // Re-build the validated category id list (only those the user can access).
        $catIds = array_map(fn ($r) => (int) $r['id'], $allowedRows);

        $title = trim((string) ($_POST['title'] ?? pathinfo($file['name'], PATHINFO_FILENAME)));
        if ($title === '') $this->jsonError('Title is required.');

        // Compute file hash for dedup
        $hash = hash_file('sha256', $file['tmp_name']);
        if ($existing = Media::findByHash($hash)) {
            ActivityLog::record('media.upload.duplicate', 'media', (int) $existing['id'], ['hash' => $hash]);
            $this->jsonOk([
                'duplicate' => true,
                'media' => [
                    'uuid' => $existing['uuid'],
                    'url'  => url('/media/' . $existing['uuid']),
                ],
                'message' => 'This file is already in the library.',
            ]);
        }

        // Persist physical file
        $type   = MediaProcessor::classify($mime);
        $uuid   = $this->uuidv4();
        $ext    = $this->extFor($mime, (string) $file['name']);
        $relDir = '/uploads/originals/' . date('Y/m');
        $absDir = storage_path($relDir);
        if (!is_dir($absDir) && !mkdir($absDir, 0775, true) && !is_dir($absDir)) {
            $this->jsonError('Could not create storage directory.');
        }
        $relPath = $relDir . '/' . $uuid . '.' . $ext;
        $absPath = storage_path($relPath);
        if (!move_uploaded_file($file['tmp_name'], $absPath)) {
            $this->jsonError('Could not move uploaded file.');
        }

        // Generate previews
        [$thumbRel, $previewRel, $hlsMasterRel, $duration, $w, $h] = $this->processMedia($absPath, $mime, $type, $uuid);

        // Insert DB row
        $mediaId = Media::create([
            'uuid'              => $uuid,
            'section_id'        => $primarySectionId,
            'title'             => $title,
            'description'       => $_POST['description'] ?? null,
            'keywords'          => $_POST['keywords'] ?? null,
            'media_type'        => $type,
            'mime_type'         => $mime,
            'file_path'         => $relPath,
            'file_size'         => filesize($absPath) ?: 0,
            'file_hash'         => $hash,
            'thumbnail_path'    => $thumbRel,
            'preview_path'      => $previewRel,
            'hls_master'        => $hlsMasterRel,
            'duration_sec'      => $duration,
            'width'             => $w,
            'height'            => $h,
            'is_downloadable'   => !empty($_POST['is_downloadable']),
            'uploaded_by'       => Auth::id() ?? 0,
            'processing_status' => 'ready',
        ]);

        // Categories / occasions / tags
        Media::attachCategories($mediaId, $catIds);

        $occs = array_filter((array) ($_POST['occasions'] ?? []));
        if ($occs) Media::attachOccasions($mediaId, $occs);

        $tagsCsv = (string) ($_POST['tags_csv'] ?? '');
        if ($tagsCsv !== '') {
            $names  = array_filter(array_map('trim', explode(',', $tagsCsv)));
            $tagIds = Tag::findOrCreateMany($names);
            Media::attachTags($mediaId, $tagIds);
        }

        ActivityLog::record('media.upload', 'media', $mediaId, [
            'title' => $title, 'mime' => $mime, 'size' => filesize($absPath),
        ]);

        $this->jsonOk([
            'duplicate' => false,
            'media' => [
                'id'   => $mediaId,
                'uuid' => $uuid,
                'url'  => url('/media/' . $uuid),
            ],
        ]);
    }

    /** @return array{0:?string,1:?string,2:?string,3:?int,4:?int,5:?int} */
    private function processMedia(string $absPath, string $mime, string $type, string $uuid): array
    {
        $thumbRel = $previewRel = $hlsMasterRel = null;
        $duration = $w = $h = null;

        $thumbDir = '/uploads/thumbnails/' . date('Y/m');
        $absThumbDir = storage_path($thumbDir);
        if (!is_dir($absThumbDir)) @mkdir($absThumbDir, 0775, true);

        if ($type === 'image') {
            $thumbRel = $thumbDir . '/' . $uuid . '.jpg';
            MediaProcessor::imageThumbnail($absPath, storage_path($thumbRel));
            if ($info = @getimagesize($absPath)) { $w = $info[0]; $h = $info[1]; }
        } elseif ($type === 'video') {
            $thumbRel = $thumbDir . '/' . $uuid . '.jpg';
            MediaProcessor::videoThumbnail($absPath, storage_path($thumbRel));
            $duration = MediaProcessor::videoDuration($absPath);

            // Optional HLS transcode (CPU-heavy; usually queued. Phase 1: best-effort.)
            $hlsDir = '/uploads/hls/' . $uuid;
            $absHls = storage_path($hlsDir);
            if (MediaProcessor::transcodeHls($absPath, $absHls)) {
                $hlsMasterRel = $hlsDir . '/master.m3u8';
            }
        } elseif ($type === 'pdf') {
            $previewDir = '/uploads/pdf-previews/' . date('Y/m');
            if (!is_dir(storage_path($previewDir))) @mkdir(storage_path($previewDir), 0775, true);
            $previewRel = $previewDir . '/' . $uuid . '.png';
            MediaProcessor::pdfPreview($absPath, storage_path($previewRel));
            $thumbRel = $previewRel; // reuse for grid
        } elseif ($type === 'ppt') {
            $previewDir = '/uploads/ppt-previews/' . date('Y/m');
            if (!is_dir(storage_path($previewDir))) @mkdir(storage_path($previewDir), 0775, true);
            $previewRel = $previewDir . '/' . $uuid . '.png';
            MediaProcessor::pptPreview($absPath, storage_path($previewRel), storage_path('/cache'));
            $thumbRel = $previewRel;
        }

        return [$thumbRel, $previewRel, $hlsMasterRel, $duration, $w, $h];
    }

    private function detectMime(string $path, string $name): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime  = $finfo ? (finfo_file($finfo, $path) ?: '') : '';
        if ($finfo) finfo_close($finfo);

        // Refine based on extension for office docs (finfo can return zip for pptx)
        $ext = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($ext, ['pptx'], true) && in_array($mime, ['application/zip','application/x-zip-compressed'], true)) {
            return 'application/vnd.openxmlformats-officedocument.presentationml.presentation';
        }
        if ($ext === 'ppt' && $mime === 'application/octet-stream') return 'application/vnd.ms-powerpoint';
        return $mime ?: 'application/octet-stream';
    }

    private function extFor(string $mime, string $orig): string
    {
        $orig = strtolower((string) pathinfo($orig, PATHINFO_EXTENSION));
        return match ($mime) {
            'video/mp4'   => 'mp4',
            'image/png'   => 'png',
            'image/jpeg'  => 'jpg',
            'image/webp'  => 'webp',
            'image/gif'   => 'gif',
            'application/pdf' => 'pdf',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            default => $orig ?: 'bin',
        };
    }

    private function uuidv4(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    private function jsonOk(array $data): never
    {
        header('Content-Type: application/json');
        echo json_encode(['ok' => true] + $data);
        exit;
    }

    private function jsonError(string $msg, int $code = 400): never
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => $msg]);
        exit;
    }
}
