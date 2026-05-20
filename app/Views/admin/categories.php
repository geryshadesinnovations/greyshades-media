<?php
/** @var array $sections
 *  @var array $trees
 */
use App\Core\Csrf;
$this->extend('layouts/app');

$render = function (array $nodes, int $depth = 0) use (&$render) {
    foreach ($nodes as $n) {
        echo '<li class="cat-row" style="padding-left:' . ($depth * 18) . 'px">';
        echo '<span>' . e($n['name']) . ' <small class="muted">(' . e($n['slug']) . ')</small></span>';
        echo '<form method="post" action="' . url('/admin/categories/' . (int)$n['id'] . '/delete') . '" onsubmit="return confirm(\'Delete this category?\')">';
        echo \App\Core\Csrf::field();
        echo '<button class="btn-danger small" type="submit">Delete</button>';
        echo '</form>';
        echo '</li>';
        if (!empty($n['children'])) $render($n['children'], $depth + 1);
    }
};

// Flatten for parent picker
$flat = [];
$flatten = function ($nodes, $depth = 0) use (&$flatten, &$flat) {
    foreach ($nodes as $n) {
        $flat[] = ['id' => $n['id'], 'name' => str_repeat('— ', $depth) . $n['name'], 'section_id' => $n['section_id']];
        if (!empty($n['children'])) $flatten($n['children'], $depth + 1);
    }
};
foreach ($trees as $t) $flatten($t);
?>
<div class="admin-shell">
    <?php require __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
        <h1>Categories</h1>

        <details class="glass create-form" open>
            <summary>+ New category</summary>
            <form method="post" action="<?= url('/admin/categories') ?>" class="grid-form">
                <?= Csrf::field() ?>
                <label class="select"><span>Section</span>
                    <select name="section_id" required>
                        <?php foreach ($sections as $s): ?>
                            <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="select"><span>Parent (optional)</span>
                    <select name="parent_id" id="parent-select">
                        <option value="">— none (top level) —</option>
                        <?php foreach ($flat as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" data-section="<?= (int) $c['section_id'] ?>"><?= e($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label><span>Name</span><input name="name" required></label>
                <button type="submit" class="btn-primary">Create</button>
            </form>
        </details>

        <?php foreach ($sections as $s): ?>
            <h3><?= e($s['name']) ?></h3>
            <ul class="cat-list glass">
                <?php $render($trees[$s['code']] ?? []); ?>
            </ul>
        <?php endforeach; ?>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const sectionSelect = document.querySelector('select[name="section_id"]');
    const parentSelect = document.getElementById('parent-select');
    if (!sectionSelect || !parentSelect) return;
    
    const allOptions = [...parentSelect.querySelectorAll('option[data-section]')];
    
    function filterParents() {
        const selectedSection = sectionSelect.value;
        parentSelect.value = '';
        allOptions.forEach(opt => {
            opt.style.display = opt.dataset.section === selectedSection ? '' : 'none';
        });
    }
    
    sectionSelect.addEventListener('change', filterParents);
    filterParents();
});
</script>
