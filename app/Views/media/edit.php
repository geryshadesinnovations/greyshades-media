<?php
/**
 * @var array $media
 * @var array $categories  attached
 * @var array $occasions   attached
 * @var array $tags        attached
 * @var array $allOccasions
 * @var array $sections
 * @var array $trees
 */
use App\Core\Csrf;
$this->extend('layouts/app');

$attachedCatIds = array_map(fn ($c) => (int) $c['id'], $categories);
$attachedOccIds = array_map(fn ($o) => (int) $o['id'], $occasions);
$tagsCsv = implode(', ', array_map(fn ($t) => $t['name'], $tags));

$renderCatTree = function ($nodes, $depth = 0) use (&$renderCatTree, $attachedCatIds) {
    foreach ($nodes as $n) {
        $checked = in_array((int) $n['id'], $attachedCatIds, true) ? 'checked' : '';
        echo '<label class="cat-pick" style="padding-left:' . ($depth * 16) . 'px"><input type="checkbox" name="categories[]" value="' . (int) $n['id'] . '" ' . $checked . '> ' . e($n['name']) . '</label>';
        if (!empty($n['children'])) $renderCatTree($n['children'], $depth + 1);
    }
};
?>
<div class="centered-form">
    <div class="form-card glass">
        <h1>Edit media</h1>
        <p class="muted">UUID <code><?= e($media['uuid']) ?></code></p>

        <form method="post" action="<?= url('/media/' . $media['id']) ?>">
            <?= Csrf::field() ?>
            <label><span>Title</span><input name="title" value="<?= e($media['title']) ?>" required></label>
            <label><span>Description</span><textarea name="description" rows="4"><?= e($media['description']) ?></textarea></label>
            <label><span>Keywords</span><input name="keywords" value="<?= e($media['keywords']) ?>"></label>
            <label><span>Tags (comma separated)</span><input name="tags_csv" value="<?= e($tagsCsv) ?>"></label>

            <fieldset>
                <legend>Categorisation</legend>
                <?php foreach ($sections as $s): ?>
                    <h4><?= e($s['name']) ?></h4>
                    <div class="cat-tree-pick"><?php $renderCatTree($trees[$s['code']] ?? []); ?></div>
                <?php endforeach; ?>
            </fieldset>

            <fieldset>
                <legend>Occasions</legend>
                <div class="occasion-grid">
                <?php foreach ($allOccasions as $code => $g): ?>
                    <details>
                        <summary><?= e($g['name']) ?></summary>
                        <?php foreach ($g['items'] as $o): $checked = in_array((int)$o['id'], $attachedOccIds, true) ? 'checked' : ''; ?>
                        <label class="cat-pick"><input type="checkbox" name="occasions[]" value="<?= (int)$o['id'] ?>" <?= $checked ?>> <?= e($o['name']) ?></label>
                        <?php endforeach; ?>
                    </details>
                <?php endforeach; ?>
                </div>
            </fieldset>

            <fieldset>
                <legend>Permissions</legend>
                <label class="cat-pick"><input type="checkbox" name="is_downloadable" value="1" <?= $media['is_downloadable'] ? 'checked' : '' ?>> Allow downloads (when user has download permission)</label>
                <label class="cat-pick"><input type="checkbox" name="is_featured" value="1" <?= $media['is_featured'] ? 'checked' : '' ?>> Featured on dashboard</label>
                <label class="cat-pick"><input type="checkbox" name="is_pinned" value="1" <?= $media['is_pinned'] ? 'checked' : '' ?>> Pin to top</label>
            </fieldset>

            <div class="form-actions">
                <a class="btn-ghost" href="<?= url('/media/' . $media['uuid']) ?>">Cancel</a>
                <button type="submit" class="btn-primary">Save changes</button>
            </div>
        </form>
    </div>
</div>
