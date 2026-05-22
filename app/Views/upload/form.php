<?php
/**
 * @var array $sections
 * @var array $trees
 * @var int $maxMb
 * @var array $allowed
 */
use App\Core\Csrf;
$this->extend('layouts/app');

/**
 * Render a top-level category card (Gimmick / Art / Hybrid / Events).
 * Each card has its own scrollable body and a data-cat-root attribute that
 * the client uses to enforce mutual-exclusion rules between Gimmick and Art.
 *
 * NOTE: We no longer render an "All <Root>" master checkbox at the top of the
 * card — the user picks specific subcategories and the backend auto-attaches
 * the parent / root / section behind the scenes. The card header is now
 * label-only (the root category is selected implicitly via its children).
 */
$renderRootCard = function (array $node, string $sectionName, string $sectionCode, ?string $exclusiveGroup = null) use (&$renderTree) {
    $rootSlug = (string) $node['slug'];
    echo '<div class="cat-card" data-cat-root="' . e($rootSlug) . '" data-root-id="' . (int) $node['id'] . '"';
    if ($exclusiveGroup) echo ' data-exclusive="' . e($exclusiveGroup) . '"';
    echo '>';

    // Header — label only, no master checkbox
    echo '<button type="button" class="cat-card-header" data-accordion>';
    echo '<svg class="chev" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>';
    echo '<span class="cat-card-name">' . e($node['name']) . '</span>';
    echo '<span class="cat-card-section">' . e($sectionName) . '</span>';
    echo '<span class="cat-card-count" hidden>0</span>';
    echo '</button>';

    // Body (scrollable) — only subcategories, no "All <Root>" checkbox
    echo '<div class="cat-card-body">';
    if (!empty($node['children'])) {
        $renderTree($node['children'], $sectionCode, $rootSlug, $exclusiveGroup, 0);
    } else {
        // Edge case: a root with no children — let the user pick the root itself.
        echo '<label class="cat-pick">';
        echo '<input form="upload-form" type="checkbox" name="categories[]" value="' . (int)$node['id'] . '" data-section="' . e($sectionCode) . '" data-cat-root="' . e($rootSlug) . '"';
        if ($exclusiveGroup) echo ' data-exclusive="' . e($exclusiveGroup) . '"';
        echo '>';
        echo '<span class="cat-pick-label">' . e($node['name']) . '</span>';
        echo '</label>';
    }
    echo '</div>';
    echo '</div>';
};

/**
 * Render the children of a category card recursively. The data-section /
 * data-cat-root / data-exclusive attributes propagate down so all checkboxes
 * inside a card share the same exclusion group.
 *
 * IMPORTANT: Only LEAF categories (those with no children) get a selectable
 * checkbox. Parent/intermediate categories are rendered as collapsible
 * accordion headers (label only, no checkbox). The backend auto-attaches
 * all ancestors when the user picks a leaf, so filtering by any level works.
 */
$renderTree = function (array $nodes, string $sectionCode, string $rootSlug, ?string $exclusiveGroup, int $depth) use (&$renderTree) {
    foreach ($nodes as $n) {
        $hasChildren = !empty($n['children']);
        $indent = $depth * 14;
        $extraAttrs = ' data-section="' . e($sectionCode) . '" data-cat-root="' . e($rootSlug) . '"';
        if ($exclusiveGroup) $extraAttrs .= ' data-exclusive="' . e($exclusiveGroup) . '"';

        if ($hasChildren) {
            // Parent category — render as non-selectable accordion header
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
            echo '<label class="cat-pick" style="margin-left:' . $indent . 'px">';
            echo '<input form="upload-form" type="checkbox" name="categories[]" value="' . (int)$n['id'] . '"' . $extraAttrs . '>';
            echo '<span class="cat-pick-label">' . e($n['name']) . '</span>';
            echo '</label>';
        }
    }
};

// Sort top-level categories so Hybrid sits between Gimmick/Art and Events.
$rootCards = [];
foreach ($sections as $s) {
    foreach ($trees[$s['code']] ?? [] as $rootNode) {
        $rootCards[] = [
            'node'             => $rootNode,
            'section_code'     => (string) $s['code'],
            'section_name'     => (string) $s['name'],
            // Mutual-exclusion: Gimmick and Art cancel each other out.
            'exclusive_group'  => in_array($rootNode['slug'], ['gimmick','art'], true) ? 'gimmick-art' : null,
        ];
    }
}
?>
<div class="upload-page">
    <div class="upload-header">
        <h1>Upload media</h1>
        <p class="muted">Drop a file, pick the categories it belongs to, and we'll do the rest. The same file is stored once and shows up in every category you select.</p>
    </div>

    <div class="upload-grid">
        <!-- Left: drop zone + preview -->
        <div class="upload-left">
            <form id="upload-form" class="upload-dropzone glass" enctype="multipart/form-data" method="post" action="<?= url('/upload') ?>">
                <?= Csrf::field() ?>

                <div id="drop-area" class="drop-area">
                    <input type="file" id="file-input" name="file" accept=".mp4,.png,.jpg,.jpeg,.webp,.gif,.pdf,.ppt,.pptx" hidden>
                    <svg width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
                    <h3>Drag &amp; drop a file</h3>
                    <p>or <button type="button" class="link-btn" id="browse-btn">browse from your device</button></p>
                    <p class="muted small">MP4 · PNG · JPG · WEBP · PDF · PPT · PPTX</p>
                    <div id="file-info" class="file-info" hidden></div>
                    <div id="progress-wrap" class="progress" hidden><div id="progress-bar"></div></div>
                    <div id="upload-result" class="upload-result" hidden></div>
                </div>

                <!-- Thumbnail dropzone — same look as the main file dropzone.
                     Hidden by default; revealed by upload.js only when the
                     selected main file is video / ppt / pptx / pdf. -->
                <div id="thumbnail-area" class="drop-area drop-area-thumbnail" hidden>
                    <input type="file" id="thumbnail-input" name="thumbnail" accept=".jpg,.jpeg,.png,.webp" hidden>
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 16l5-5 4 4 4-3 5 4"/><circle cx="9" cy="9" r="1.5"/></svg>
                    <h3>Drag &amp; drop a thumbnail</h3>
                    <p>or <button type="button" class="link-btn" id="thumb-browse-btn">browse for a thumbnail image</button></p>
                    <p class="muted small">Required for Video / PPT / PDF · JPG · PNG · WEBP</p>
                    <div id="thumb-info" class="file-info" hidden></div>
                    <img id="thumb-preview" alt="Thumbnail preview" hidden>
                </div>
            </form>

            <!-- Media preview before upload -->
            <div id="upload-preview" class="upload-preview">
                <video id="preview-video" controls style="display:none; max-width:100%; max-height:300px;"></video>
                <img id="preview-image" style="display:none; max-width:100%; max-height:300px;" alt="Preview">
                <div id="preview-pdf" class="pdf-preview-msg" style="display:none">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><path d="M13 2v7h7"/></svg>
                    <p>PDF file selected — preview available after upload.</p>
                </div>
                <div id="preview-ppt" class="ppt-preview-msg" style="display:none">
                    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
                    <p>Presentation file selected — first-slide preview generated after upload.</p>
                </div>
            </div>
        </div>

        <!-- Right: metadata & settings -->
        <aside class="upload-meta glass">
            <!-- Section 1: Basic Info -->
            <div class="form-section">
                <div class="form-section-title">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                    Basic information
                </div>
                <div class="form-section-body">
                    <label><span>Title</span><input form="upload-form" type="text" name="title" placeholder="Auto-fills from filename" required></label>
                    <label><span>Description</span><textarea form="upload-form" name="description" rows="3"></textarea></label>
                    <label><span>Keywords</span><input form="upload-form" type="text" name="keywords" placeholder="Separate with commas"></label>

                    <label><span>Company</span>
                    <select form="upload-form" name="company_id">
                        <option value="">— None —</option>
                        <?php foreach ($companies as $co): ?>
                        <option value="<?= (int)$co['id'] ?>"><?= e($co['name']) ?></option>
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
                        Pick the specific subcategories your file belongs to — the parent
                        category (Gimmick / Art / Hybrid / Events) is assigned automatically.
                        <br><strong>Note:</strong> Gimmick and Art are mutually exclusive — Hybrid and Events can mix freely.
                        Hybrid subcategories already cover all occasions (medical days, national festivals, etc.), so there's no separate Occasion picker.
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
                        <input form="upload-form" type="checkbox" name="is_downloadable" value="1">
                        <span class="cat-pick-label">Allow downloads</span>
                    </label>
                </div>
            </div>

            <!-- Sticky upload button -->
            <div class="upload-submit-wrap">
                <button type="button" id="upload-submit-btn" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 19V5M5 12l7-7 7 7"/></svg>
                    Upload file
                </button>
            </div>
        </aside>
    </div>
</div>

<?php $this->section('scripts'); ?>
<script src="<?= asset('js/upload.js') ?>" defer></script>
<?php $this->endSection(); ?>
