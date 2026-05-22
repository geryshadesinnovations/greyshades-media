<?php
/** @var array $companies */
use App\Core\Csrf;
$this->extend('layouts/app');
?>
<div class="admin-shell">
    <?php require __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
        <h1>Companies</h1>

        <details class="glass create-form" open>
            <summary>+ New company</summary>
            <form method="post" action="<?= url('/admin/companies') ?>" class="grid-form">
                <?= Csrf::field() ?>
                <label><span>Name</span><input name="name" required></label>
                <button type="submit" class="btn-primary">Create</button>
            </form>
        </details>

        <ul class="cat-list glass">
            <?php foreach ($companies as $co): ?>
            <li class="cat-row">
                <span><?= e($co['name']) ?> <small class="muted">(<?= e($co['slug']) ?>)</small></span>
                <form method="post" action="<?= url('/admin/companies/' . (int)$co['id'] . '/delete') ?>" onsubmit="return confirm('Delete this company?')">
                    <?= Csrf::field() ?>
                    <button class="btn-danger small" type="submit">Delete</button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>
