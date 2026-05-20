<?php
/**
 * @var array $stats
 * @var array $recent
 * @var array $live
 * @var array $topViewed
 * @var array $topDownloaded
 */
$this->extend('layouts/app');
?>
<div class="admin-shell">
    <?php require __DIR__ . '/_nav.php'; ?>
    <section class="admin-content">
        <h1>Admin Overview</h1>

        <div class="stat-grid">
            <div class="stat-card glass"><span class="stat-label">Total media</span><span class="stat-value"><?= number_format($stats['media_total']) ?></span></div>
            <div class="stat-card glass"><span class="stat-label">Storage used</span><span class="stat-value"><?= e(format_bytes($stats['storage_bytes'])) ?></span></div>
            <div class="stat-card glass"><span class="stat-label">Active users</span><span class="stat-value"><?= number_format($stats['users_total']) ?></span></div>
            <div class="stat-card glass"><span class="stat-label">Live sessions (15m)</span><span class="stat-value"><?= number_format($stats['sessions_live']) ?></span></div>
            <div class="stat-card glass"><span class="stat-label">Views (30d)</span><span class="stat-value"><?= number_format($stats['views_30d']) ?></span></div>
            <div class="stat-card glass"><span class="stat-label">Downloads (30d)</span><span class="stat-value"><?= number_format($stats['downloads_30d']) ?></span></div>
        </div>

        <div class="stat-grid stat-grid-small">
            <div class="stat-card glass"><span class="stat-label">Videos</span><span class="stat-value"><?= number_format($stats['media_video']) ?></span></div>
            <div class="stat-card glass"><span class="stat-label">Images</span><span class="stat-value"><?= number_format($stats['media_image']) ?></span></div>
            <div class="stat-card glass"><span class="stat-label">PDFs</span><span class="stat-value"><?= number_format($stats['media_pdf']) ?></span></div>
            <div class="stat-card glass"><span class="stat-label">Presentations</span><span class="stat-value"><?= number_format($stats['media_ppt']) ?></span></div>
        </div>

        <div class="admin-cols">
            <section class="glass">
                <h3>Live sessions</h3>
                <?php if (empty($live)): ?>
                    <p class="muted">No active sessions.</p>
                <?php else: ?>
                <table class="table">
                    <thead><tr><th>User</th><th>IP</th><th>Currently viewing</th><th>Last activity</th></tr></thead>
                    <tbody>
                    <?php foreach ($live as $s): ?>
                        <tr>
                            <td><?= e($s['name']) ?><br><small class="muted"><?= e($s['email']) ?></small></td>
                            <td><?= e($s['ip_address']) ?></td>
                            <td><?php if ($s['media_uuid']): ?><a href="<?= url('/media/' . $s['media_uuid']) ?>"><?= e($s['media_title']) ?></a><?php else: ?><span class="muted">Browsing</span><?php endif; ?></td>
                            <td><?= e($s['last_activity_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </section>

            <section class="glass">
                <h3>Recent activity</h3>
                <ul class="activity-list">
                    <?php foreach ($recent as $a): ?>
                    <li>
                        <span class="action"><?= e($a['action']) ?></span>
                        <span class="muted"><?= e($a['user_name'] ?? 'system') ?></span>
                        <small><?= e($a['created_at']) ?></small>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        </div>

        <div class="admin-cols">
            <section class="glass">
                <h3>Top viewed</h3>
                <ol class="rank-list">
                    <?php foreach ($topViewed as $m): ?>
                    <li><span class="rank-title"><?= e($m['title']) ?></span><span class="rank-num"><?= number_format($m['view_count']) ?> views</span></li>
                    <?php endforeach; ?>
                </ol>
            </section>
            <section class="glass">
                <h3>Top downloaded</h3>
                <ol class="rank-list">
                    <?php foreach ($topDownloaded as $m): ?>
                    <li><span class="rank-title"><?= e($m['title']) ?></span><span class="rank-num"><?= number_format($m['download_count']) ?></span></li>
                    <?php endforeach; ?>
                </ol>
            </section>
        </div>
    </section>
</div>
