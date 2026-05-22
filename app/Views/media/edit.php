<?php
/**
 * Edit Media — mirrors the Upload form layout exactly.
 * Only leaf categories are selectable (no parent-category checkboxes).
 * No occasion selection. No tags.
 *
 * @var array $media
 * @var array $categories  currently attached categories
 * @var array $sections
 * @var array $trees
 */
use App\Core\Csrf;
$this->extend('layouts/app');

$attachedCatIds = array_map(fn ($c) => (int) $c['id'], $categories);

/**
 * Render a top-level category card (Gimmick / Art / Hybrid / Events).
 * Header is label-only (no checkbox). Body shows the subcategory tree.
 */
$renderRootCard = function (array $node, string $sectionName, string $sectionCode, ?string $exclusiveGroup = null) use (&$renderTree) {
    $rootSlug = (string) $node['slug'];
    echo '<div class="cat-card" data-cat-root="' . e($rootSlug) . '" data-root-id="' . (int) $node['id'] . '"';
    if ($exclusiveGroup) echo ' data-exclusive="' . e($exclusiveGroup) . '"';
    echo '>';

    echo '<button type="button" class="cat-card-header" data-accordion>';
    echo '<svg class="chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>';
    echo '<span class="cat-card-name">' . e($node['name']) . '</span>';
    echo '<span class="cat-card-section">' . e($sectionName) . '</span>';
    echo '<span class="cat-card-count" hidden>0</span>';
    echo '</button>';

    echo '<div class="cat-card-body">';
    if (!empty($node['children'])) {
        $renderTree($node['children'], $sectionCode, $rootSlug, $exclusiveGroup, 0);
    } else {
        // Root with no children — let the user pick it directly
        echo '<label class="cat-pick">';
        echo '<input type="checkbox" name="categories[]" value="' . (int)$node['id'] . '" data-section="' . e($sectionCode) . '" data-cat-root="' . e($rootSlug) . '"';
        if ($exclusiveGroup) echo ' data-exclusive="' . e($exclusiveGroup) . '"';
        echo '>';
        echo '<span class="cat-pick-label">' . e($node['name']) . '</span>';
        echo '</label>';
    }
    echo '</div>';
    echo '</div>';
};

/**
 * Render the tree recursively. Only LEAF categories get checkboxes.
 * Parent/intermediate categories are non-selectable accordion headers.
 */
$renderTree = function (array $nodes, string $sectionCode, string $rootSlug, ?string $exclusiveGroup, int $depth) use (&$renderTree, $attachedCatIds) {
    foreach ($nodes as $n) {
        $hasChildren = !empty($n['children']);
        $indent = $depth * 14;
        $extraAttrs = ' data-section="' . e($sectionCode) . '" data-cat-root="' . e($rootSlug) . '"';
        if ($exclusiveGroup) $extraAttrs .= ' data-exclusive="' . e($exclusiveGroup) . '"';

        if ($hasChildren) {
            // Parent category — non-selectable accordion header
            echo '<div class="cat-sub" style="margin-left:' . $indent . 'px">';
            echo '<button type="button" class="cat-sub-header" data-accordion>';
            echo '<svg class="chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>';
            echo '<span class="cat-pick-label" style="font-weight:600">' . e($n['name']) . '</span>';
            echo '</button>';
            echo '<div class="cat-sub-body">';
            $renderTree($n['children'], $sectionCode, $rootSlug, $exclusiveGroup, $depth + 1);
            echo '</div>';
            echo '</div>';
        } else {
            // Leaf category — selectable checkbox
            $checked = in_array((int) $n['id'], $attachedCatIds, true) ? ' checked' : '';
            echo '<label class="cat-pick" style="margin-left:' . $indent . 'px">';
            echo '<input type="checkbox" name="categories[]" value="' . (int)$n['id'] . '"' . $extraAttrs . $checked . '>';
            echo '<span class="cat-pick-label">' . e($n['name']) . '</span>';
            echo '</label>';
        }
    }
};

// Build root cards exactly like the upload form
$rootCards = [];
foreach ($sections as $s) {
    foreach ($trees[$s['code']] ?? [] as $rootNode) {
        $rootCards[] = [
            'node'             => $rootNode,
            'section_code'     => (string) $s['code'],
            'section_name'     => (string) $s['name'],
            'exclusive_group'  => in_array($rootNode['slug'], ['gimmick','art'], true) ? 'gimmick-art' : null,
        ];
    }
}
?>
<div class="upload-page">
    <div class="upload-header">
        <h1>Edit media</h1>
        <p class="muted">UUID <code><?= e($media['uuid']) ?></code> · Uploaded <?= e(date('d M Y', strtotime((string) $media['created_at']))) ?></p>
    </div>

    <div class="upload-grid">
        <!-- Left: current media preview -->
        <div class="upload-left">
            <div class="upload-dropzone glass" style="padding: 1.5rem; text-align: center;">
                <?php if ($media['media_type'] === 'video'): ?>
                    <video controls style="max-width:100%; max-height:360px; border-radius: 12px;"
                           poster="<?= url('/thumb/' . $media['uuid']) ?>"
                           src="<?= url('/stream/' . $media['uuid'] . '?token=' . \App\Core\StreamToken::issue((int)$media['id'])) ?>"></video>
                <?php elseif ($media['media_type'] === 'image'): ?>
                    <img src="<?= url('/stream/' . $media['uuid'] . '?token=' . \App\Core\StreamToken::issue((int)$media['id'])) ?>"
                         alt="<?= e($media['title']) ?>" style="max-width:100%; max-height:360px; border-radius: 12px;">
                <?php elseif ($media['thumbnail_path']): ?>
                    <img src="<?= url('/thumb/' . $media['uuid']) ?>"
                         alt="<?= e($media['title']) ?>" style="max-width:100%; max-height:360px; border-radius: 12px;">
                <?php else: ?>
                    <div style="padding: 3rem 1rem; color: var(--text-muted);">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 16l5-5 4 4 4-3 5 4"/></svg>
                        <p>No preview available</p>
                    </div>
                <?php endif; ?>

                <div style="margin-top: 1rem; text-align: left;">
                    <div class="info-meta" style="justify-content: center; flex-wrap: wrap; gap: .4rem;">
                        <span class="badge"><?= strtoupper($media['media_type']) ?></span>
                        <span class="badge soft"><?= e($media['mime_type']) ?></span>
                        <span class="badge soft"><?= e(format_bytes((int) $media['file_size'])) ?></span>
                        <?php if (!empty($media['duration_sec'])): ?>
                            <span class="badge soft"><?= e(format_duration($media['duration_sec'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right: metadata & categories (same layout as upload form) -->
        <aside class="upload-meta glass">
            <form id="edit-form" method="post" action="<?= url('/media/' . $media['id']) ?>">
                <?= Csrf::field() ?>

                <!-- Section 1: Basic Info -->
                <div class="form-section">
                    <div class="form-section-title">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        Basic information
                    </div>
                    <div class="form-section-body">
                        <label><span>Title</span><input type="text" name="title" value="<?= e($media['title']) ?>" required></label>
                        <label><span>Description</span><textarea name="description" rows="3"><?= e($media['description']) ?></textarea></label>
                        <label><span>Keywords</span><input type="text" name="keywords" value="<?= e($media['keywords']) ?>" placeholder="Separate with commas"></label>
                        <label><span>Company</span>
                        <select name="company_id">
                            <option value="">— None —</option>
                            <?php foreach ($companies as $co): ?>
                            <option value="<?= (int)$co['id'] ?>" <?= ((int)($media['company_id'] ?? 0)) === (int)$co['id'] ? 'selected' : '' ?>><?= e($co['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        </label>
                    </div>
                </div>

                <!-- Section 2: Where does this go? (categories) -->
                <div class="form-section">
                    <div class="form-section-title">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>
                        Where does this go?
                    </div>
                    <div class="form-section-body">
                        <p class="form-hint">
                            Pick the specific subcategories — parent categories are assigned automatically.
                            <br><strong>Note:</strong> Gimmick and Art are mutually exclusive.
                        </p>

                        <div id="cat-summary" class="cat-summary" hidden>
                            <span class="cat-summary-label">Selected:</span>
                            <span class="cat-summary-chips"></span>
                        </div>

                        <div class="cat-grid">
                            <?php foreach ($rootCards as $rc): ?>
                                <?php $renderRootCard($rc['node'], $rc['section_name'], $rc['section_code'], $rc['exclusive_group']); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Section 3: Settings -->
                <div class="form-section">
                    <div class="form-section-title">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        Settings
                    </div>
                    <div class="form-section-body">
                        <label class="cat-pick">
                            <input type="checkbox" name="is_downloadable" value="1" <?= $media['is_downloadable'] ? 'checked' : '' ?>>
                            <span class="cat-pick-label">Allow downloads</span>
                        </label>
                        <label class="cat-pick">
                            <input type="checkbox" name="is_featured" value="1" <?= $media['is_featured'] ? 'checked' : '' ?>>
                            <span class="cat-pick-label">Featured on dashboard</span>
                        </label>
                        <label class="cat-pick">
                            <input type="checkbox" name="is_pinned" value="1" <?= $media['is_pinned'] ? 'checked' : '' ?>>
                            <span class="cat-pick-label">Pin to top</span>
                        </label>
                    </div>
                </div>

                <!-- Sticky save button -->
                <div class="upload-submit-wrap">
                    <button type="submit" class="btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/></svg>
                        Save changes
                    </button>
                </div>
            </form>

            <div style="padding: 0 .85rem .85rem; display: flex; gap: .5rem;">
                <a class="btn-ghost" href="<?= url('/media/' . $media['uuid']) ?>" style="flex:1; justify-content: center;">Cancel</a>
                <form method="post" action="<?= url('/media/' . $media['id'] . '/delete') ?>" onsubmit="return confirm('Delete this media permanently?')" style="flex:1;">
                    <?= Csrf::field() ?>
                    <button type="submit" class="btn-danger" style="width:100%; justify-content: center;">Delete</button>
                </form>
            </div>
        </aside>
    </div>
</div>

<?php $this->section('scripts'); ?>
<script src="<?= asset('js/upload.js') ?>" defer></script>
<?php $this->endSection(); ?>
