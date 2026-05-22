<?php
/**
 * @var array $sections
 * @var array $trees
 * @var array $occasions
 * @var array $result
 * @var array $filters
 * @var string $sort
 * @var array $mediaTypes
 * @var array $companies
 */
$this->extend('layouts/app');
$rows  = $result['rows'];
$total = $result['total'];
$page  = $result['page'];
$pages = $result['pages'];

/**
 * Build a URL with the current filters preserved, overriding/removing the
 * keys passed in $changes. Pass null as a value to drop a key.
 */
$buildUrl = function (array $changes = []) use ($filters, $sort): string {
    $qs = array_filter([
        'section'  => $filters['section_code'] ?? null,
        'category' => $filters['category_id']  ?? null,
        'occasion' => $filters['occasion_id']  ?? null,
        'type'     => $filters['media_type']   ?? null,
        'q'        => $filters['q']            ?? null,
        'company'  => $filters['company_id']   ?? null,
        'sort'     => $sort !== 'newest' ? $sort : null,
    ], fn ($v) => $v !== null && $v !== '' && $v !== 0);
    foreach ($changes as $k => $v) {
        if ($v === null || $v === '' || $v === 0) {
            unset($qs[$k]);
        } else {
            $qs[$k] = $v;
        }
    }
    return '?' . http_build_query($qs);
};
?>
<div class="dashboard">
    <button class="sidebar-toggle" id="sidebar-toggle" type="button" aria-label="Toggle sidebar">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18M3 12h18M3 18h18"/></svg>
        Categories
    </button>

    <?php require __DIR__ . '/_sidebar.php'; ?>

    <section class="content">
        <!-- Active filter chips: lets users see and remove individual filters -->
        <?php
            $activeChips = [];
            if (!empty($filters['q']))            $activeChips[] = ['label' => 'Search: "' . $filters['q'] . '"',                'href' => $buildUrl(['q' => null])];
            if (!empty($filters['section_code'])) $activeChips[] = ['label' => 'Section: ' . ucfirst((string) $filters['section_code']), 'href' => $buildUrl(['section' => null])];
            if (!empty($filters['category_id'])) {
                $catName = (string) (\App\Core\Database::scalar('SELECT name FROM categories WHERE id = ?', [(int) $filters['category_id']]) ?? 'Category');
                $activeChips[] = ['label' => 'Category: ' . $catName, 'href' => $buildUrl(['category' => null])];
            }
            if (!empty($filters['occasion_id'])) {
                $occName = (string) (\App\Core\Database::scalar('SELECT name FROM occasions WHERE id = ?', [(int) $filters['occasion_id']]) ?? 'Occasion');
                $activeChips[] = ['label' => 'Occasion: ' . $occName, 'href' => $buildUrl(['occasion' => null])];
            }
            if (!empty($filters['media_type']))   $activeChips[] = ['label' => 'Type: ' . ucfirst((string) $filters['media_type']), 'href' => $buildUrl(['type' => null])];
            if (!empty($filters['company_id'])) {
                $compName = (string) (\App\Core\Database::scalar('SELECT name FROM companies WHERE id = ?', [(int) $filters['company_id']]) ?? 'Company');
                $activeChips[] = ['label' => 'Company: ' . $compName, 'href' => $buildUrl(['company' => null])];
            }
        ?>

        <div class="filterbar glass">
            <form class="filter-form" method="get" id="filter-form">
                <?php /* preserve current filters as hidden inputs so each select-change submits the FULL filter set */ ?>
                <?php if (!empty($filters['q'])): ?>       <input type="hidden" name="q"        value="<?= e($filters['q']) ?>"><?php endif; ?>
                <?php if (!empty($filters['category_id'])): ?><input type="hidden" name="category" value="<?= (int) $filters['category_id'] ?>"><?php endif; ?>
                <?php if (!empty($filters['company_id'])): ?><input type="hidden" name="company" value="<?= (int) $filters['company_id'] ?>"><?php endif; ?>

                <label class="select">
                    <span>Section</span>
                    <select name="section" onchange="this.form.submit()">
                        <option value="">All sections</option>
                        <?php foreach ($sections as $s): ?>
                        <option value="<?= e($s['code']) ?>" <?= ($filters['section_code'] ?? null) === $s['code'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="select">
                    <span>Type</span>
                    <select name="type" onchange="this.form.submit()">
                        <option value="">All types</option>
                        <?php foreach ($mediaTypes as $k => $v): ?>
                        <option value="<?= e($k) ?>" <?= ($filters['media_type'] ?? null) === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="select">
                    <span>Company</span>
                    <select name="company" onchange="this.form.submit()">
                        <option value="">All companies</option>
                        <?php foreach ($companies as $co): ?>
                        <option value="<?= (int)$co['id'] ?>" <?= ((int)($filters['company_id'] ?? 0)) === (int)$co['id'] ? 'selected' : '' ?>><?= e($co['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <?php if (!empty($filters['occasion_id'])): ?>
                    <input type="hidden" name="occasion" value="<?= (int) $filters['occasion_id'] ?>">
                <?php endif; ?>

                <label class="select">
                    <span>Sort by</span>
                    <select name="sort" onchange="this.form.submit()">
                        <?php foreach (['newest'=>'Newest','oldest'=>'Oldest','popular'=>'Most viewed','az'=>'A → Z'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= $sort === $k ? 'selected' : '' ?>><?= $v ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <?php if (!empty($activeChips)): ?>
                    <a class="btn-ghost clear-all-btn" href="<?= url('/dashboard') ?>">Clear all</a>
                <?php endif; ?>
            </form>

            <?php if (!empty($activeChips)): ?>
            <div class="active-filter-strip">
                <span class="active-label">Active filters:</span>
                <?php foreach ($activeChips as $chip): ?>
                <a class="chip active removable" href="<?= e($chip['href']) ?>" title="Remove this filter">
                    <?= e($chip['label']) ?>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <div class="content-header">
            <h2>
                <?php if (!empty($filters['q'])): ?>Search results for "<?= e($filters['q']) ?>"
                <?php elseif (!empty($filters['section_code']) || !empty($filters['category_id']) || !empty($filters['occasion_id']) || !empty($filters['media_type']) || !empty($filters['company_id'])): ?>Filtered media
                <?php else: ?>All media<?php endif; ?>
            </h2>
            <span class="muted"><?= number_format($total) ?> item<?= $total === 1 ? '' : 's' ?></span>
        </div>

        <?php if (empty($rows)): ?>
        <div class="empty-state glass">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M3 16l5-5 4 4 4-3 5 4"/><circle cx="9" cy="9" r="1.5"/></svg>
            <h3>No media here yet</h3>
            <p>Try adjusting your filters or upload a new asset to get started.</p>
            <?php if (\App\Core\Auth::canUpload()): ?>
            <a class="btn-primary" href="<?= url('/upload') ?>">Upload media</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="media-grid">
            <?php foreach ($rows as $m) { include __DIR__ . '/_card.php'; } ?>
        </div>

        <?php if ($pages > 1):
            $qs = $_GET;
            $build = function ($p) use ($qs) { $qs['page'] = $p; return '?' . http_build_query($qs); };
        ?>
        <nav class="pagination">
            <a class="<?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $build(max(1,$page-1)) ?>">‹ Prev</a>
            <span>Page <?= $page ?> of <?= $pages ?></span>
            <a class="<?= $page >= $pages ? 'disabled' : '' ?>" href="<?= $build(min($pages,$page+1)) ?>">Next ›</a>
        </nav>
        <?php endif; ?>

        <?php endif; ?>
    </section>
</div>
