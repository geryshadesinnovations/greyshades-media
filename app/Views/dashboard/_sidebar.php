<?php
/**
 * @var array<int,array> $sections
 * @var array<string,array> $trees       map sectionCode => nested category tree
 * @var array $filters
 * @var string $sort
 */
$selectedCat = isset($filters['category_id']) ? (int) $filters['category_id'] : 0;
$selectedSec = $filters['section_code'] ?? null;

// Build a URL preserving non-conflicting filters (e.g. clicking a category
// keeps the active occasion/type/sort but replaces any prior category
// or section selection). When a category is picked, the section filter is
// automatically cleared since the category already implies a section.
$navUrl = function (array $changes) use ($filters, $sort): string {
    $qs = array_filter([
        'occasion' => $filters['occasion_id'] ?? null,
        'type'     => $filters['media_type']  ?? null,
        'q'        => $filters['q']           ?? null,
        'sort'     => $sort !== 'newest' ? $sort : null,
    ], fn ($v) => $v !== null && $v !== '' && $v !== 0);
    foreach ($changes as $k => $v) {
        if ($v === null || $v === '' || $v === 0) {
            unset($qs[$k]);
        } else {
            $qs[$k] = $v;
        }
    }
    // If a category is being set, don't also send section — category is more specific
    if (!empty($qs['category'])) {
        unset($qs['section']);
    }
    return '?' . http_build_query($qs);
};

// Check if a category or any of its descendants is selected
$isInPath = function (array $node) use (&$isInPath, $selectedCat): bool {
    if ($selectedCat === (int) $node['id']) return true;
    foreach ($node['children'] ?? [] as $child) {
        if ($isInPath($child)) return true;
    }
    return false;
};

$renderNode = function (array $node, int $depth = 0) use (&$renderNode, $selectedCat, &$isInPath, $navUrl) {
    $active = $selectedCat === (int) $node['id'];
    $hasChildren = !empty($node['children']);
    $inPath = $hasChildren && $isInPath($node);
    ?>
    <li class="tree-item <?= $hasChildren ? 'has-children' : '' ?>">
        <div class="tree-row">
            <?php if ($hasChildren): ?>
            <button class="tree-toggle <?= $inPath ? 'open' : '' ?>" data-toggle="tree" type="button" aria-label="Toggle">
                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
            </button>
            <?php else: ?><span class="tree-spacer"></span><?php endif; ?>
            <a class="tree-link <?= $active ? 'active' : '' ?>"
               href="<?= e($navUrl(['category' => (int) $node['id']])) ?>">
                <?= e($node['name']) ?>
            </a>
        </div>
        <?php if ($hasChildren): ?>
        <ul class="tree-children" <?= $inPath ? '' : 'hidden' ?>>
            <?php foreach ($node['children'] as $child) $renderNode($child, $depth + 1); ?>
        </ul>
        <?php endif; ?>
    </li>
    <?php
};
?>
<aside class="sidebar">
    <div class="sidebar-section">
        <a class="sidebar-home <?= empty($filters['section_code']) && empty($filters['category_id']) ? 'active' : '' ?>" href="<?= url('/dashboard') ?>">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
            All media
        </a>
    </div>

    <?php foreach ($sections as $s): ?>
    <div class="sidebar-section">
        <h4 class="sidebar-title">
            <a href="<?= e($navUrl(['section' => (string) $s['code'], 'category' => null])) ?>"
               class="<?= ($selectedSec === $s['code'] && !$selectedCat) ? 'active' : '' ?>">
                <?php if ($s['code'] === 'graphics'): ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 3v18M3 12h18"/></svg>
                <?php else: ?>
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                <?php endif; ?>
                <?= e($s['name']) ?>
            </a>
        </h4>
        <ul class="tree">
            <?php foreach ($trees[$s['code']] ?? [] as $node) $renderNode($node, 0); ?>
        </ul>
    </div>
    <?php endforeach; ?>
</aside>
