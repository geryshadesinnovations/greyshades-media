<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\ActivityLog;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\StreamToken;
use App\Models\Media;
use App\Models\Section;

final class MediaController
{
    /** Single media detail / preview page. */
    public function show(string $uuid): void
    {
        $m = Media::findByUuid($uuid);
        if (!$m) { http_response_code(404); echo view('errors/404', []); return; }

        $section = Section::find((int) $m['section_id']);
        if (!$section || !Auth::canSection($section['code'])) {
            http_response_code(403); echo view('errors/403', []); return;
        }

        Media::bumpView((int) $m['id']);
        Database::execute(
            "INSERT INTO view_logs (media_id, user_id, ip_address, user_agent, session_id)
             VALUES (?,?,?,?,?)",
            [$m['id'], Auth::id(), client_ip(), substr(ua(), 0, 500), session_id()]
        );
        Database::execute(
            "UPDATE user_sessions SET current_media_id = ? WHERE id = ?",
            [$m['id'], session_id()]
        );
        ActivityLog::record('media.view', 'media', (int) $m['id']);

        // Issue a short-lived stream/download token for this media
        $streamToken = StreamToken::issue((int) $m['id']);

        echo view('media/show', [
            'media'        => $m,
            'section'      => $section,
            'categories'   => Media::categoriesFor((int) $m['id']),
            'occasions'    => Media::occasionsFor((int) $m['id']),
            'tags'         => Media::tagsFor((int) $m['id']),
            'streamToken'  => $streamToken,
            'canDownload'  => $this->isDownloadable($m),
            'canEdit'      => Auth::canEdit() || Auth::isSuperAdmin(),
            'canDelete'    => Auth::canDelete() || Auth::isSuperAdmin(),
        ]);
    }

    /** Edit form (admin/editor) */
    public function edit(int $id): void
    {
        if (!(Auth::canEdit() || Auth::isSuperAdmin())) { http_response_code(403); echo 'Forbidden'; return; }
        $m = Media::find($id);
        if (!$m) { http_response_code(404); echo view('errors/404', []); return; }

        echo view('media/edit', [
            'media'      => $m,
            'categories' => Media::categoriesFor($id),
            'occasions'  => Media::occasionsFor($id),
            'tags'       => Media::tagsFor($id),
            'allOccasions' => \App\Models\Occasion::groupedAll(),
            'sections'   => Section::all(),
            'trees'      => $this->allTrees(),
        ]);
    }

    public function update(int $id): void
    {
        Csrf::verifyOrFail();
        if (!(Auth::canEdit() || Auth::isSuperAdmin())) { http_response_code(403); echo 'Forbidden'; return; }

        $m = Media::find($id);
        if (!$m) { http_response_code(404); return; }

        Media::update($id, [
            'title'           => trim((string) ($_POST['title'] ?? $m['title'])),
            'description'     => $_POST['description'] ?? null,
            'keywords'        => $_POST['keywords'] ?? null,
            'is_downloadable' => !empty($_POST['is_downloadable']),
            'is_featured'     => !empty($_POST['is_featured']),
            'is_pinned'       => !empty($_POST['is_pinned']),
        ]);

        if (isset($_POST['categories']))
            Media::attachCategories($id, (array) $_POST['categories']);
        if (isset($_POST['occasions']))
            Media::attachOccasions($id, (array) $_POST['occasions']);
        if (isset($_POST['tags_csv'])) {
            $names = array_filter(array_map('trim', explode(',', (string) $_POST['tags_csv'])));
            $tagIds = \App\Models\Tag::findOrCreateMany($names);
            Media::attachTags($id, $tagIds);
        }

        ActivityLog::record('media.edit', 'media', $id);
        flash('success', 'Media updated.');
        redirect('/media/' . $m['uuid']);
    }

    public function destroy(int $id): void
    {
        Csrf::verifyOrFail();
        if (!(Auth::canDelete() || Auth::isSuperAdmin())) { http_response_code(403); echo 'Forbidden'; return; }
        $m = Media::find($id);
        if (!$m) { http_response_code(404); return; }

        // Remove physical files (best effort)
        foreach (['file_path','thumbnail_path','preview_path'] as $col) {
            if (!empty($m[$col])) {
                $abs = storage_path($m[$col]);
                if (is_file($abs)) @unlink($abs);
            }
        }
        if (!empty($m['hls_master'])) {
            $absDir = dirname(storage_path($m['hls_master']));
            if (is_dir($absDir)) {
                foreach (glob($absDir . '/*') ?: [] as $f) @unlink($f);
                @rmdir($absDir);
            }
        }
        Media::delete($id);
        ActivityLog::record('media.delete', 'media', $id);
        flash('success', 'Media deleted.');
        redirect('/dashboard');
    }

    private function isDownloadable(array $m): bool
    {
        if (!Auth::canDownload() && !Auth::isSuperAdmin()) {
            // Maybe granted explicitly
            $granted = Database::scalar(
                "SELECT 1 FROM media_download_grants
                 WHERE media_id = ? AND user_id = ?
                 AND (expires_at IS NULL OR expires_at > NOW())",
                [$m['id'], Auth::id()]
            );
            if (!$granted) return false;
        }
        if (!$m['is_downloadable']) {
            return Auth::isSuperAdmin();
        }
        if (!empty($m['download_expiry']) && strtotime((string) $m['download_expiry']) < time()) {
            return false;
        }
        return true;
    }

    private function allTrees(): array
    {
        $out = [];
        foreach (Section::all() as $s) {
            $out[$s['code']] = \App\Models\Category::tree((int) $s['id']);
        }
        return $out;
    }
}
