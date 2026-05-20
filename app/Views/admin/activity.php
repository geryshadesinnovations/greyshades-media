<?php
/** @var array $rows */
$this->extend('layouts/app');
?>
<div class="admin-shell">
    <?php require __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
        <h1>Activity log</h1>
        <p class="muted">Last 200 events. Includes uploads, edits, deletes, downloads, logins, and admin actions.</p>
        <table class="table glass">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th><th>Meta</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= e($r['created_at']) ?></td>
                    <td><?= e($r['user_name'] ?? '—') ?><br><small class="muted"><?= e($r['user_email'] ?? '') ?></small></td>
                    <td><span class="badge soft"><?= e($r['action']) ?></span></td>
                    <td><?= e(($r['entity_type'] ?? '') . ($r['entity_id'] ? ' #' . $r['entity_id'] : '')) ?></td>
                    <td><code><?= e($r['ip_address']) ?></code></td>
                    <td><pre class="meta"><?= e($r['meta'] ?? '') ?></pre></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
