<?php
/** @var array $m  media row */
$type = $m['media_type'];
$icon = match ($type) {
    'video' => 'M8 5v14l11-7z',
    'image' => 'M21 19V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2zM8.5 10a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3zm-3 6 4-4 3 3 4-5 5 6H5.5z',
    'pdf'   => 'M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM13 9V3.5L18.5 9zM8 13h8v2H8zm0 4h8v2H8z',
    'ppt'   => 'M3 4h18v12H3zM7 20h10M12 16v4M7 8l3 3 3-4 4 5',
    default => 'M3 7h18v10H3z',
};
$dur = format_duration($m['duration_sec'] ?? null);
?>
<a class="media-card" href="<?= url('/media/' . $m['uuid']) ?>" data-type="<?= e($type) ?>"<?php if ($type === 'video'): ?> data-preview-src="<?= url('/stream/' . $m['uuid'] . '?token=' . \App\Core\StreamToken::issue((int)$m['id'])) ?>"<?php endif; ?>>
    <div class="media-thumb">
        <img loading="lazy" src="<?= url('/thumb/' . $m['uuid']) ?>" alt="<?= e($m['title']) ?>">
        <?php if ($type === 'video'): ?>
        <div class="play-overlay">
            <svg viewBox="0 0 24 24" fill="white"><path d="M8 5v14l11-7z"/></svg>
        </div>
        <?php endif; ?>
        <span class="media-type-badge">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="<?= $icon ?>"/></svg>
            <?= strtoupper($type) ?>
        </span>
        <?php if ($dur): ?><span class="media-duration"><?= e($dur) ?></span><?php endif; ?>
        <?php if (!empty($m['is_featured'])): ?><span class="media-featured">★ Featured</span><?php endif; ?>
    </div>
    <div class="media-meta">
        <h4 class="media-title"><?= e($m['title']) ?></h4>
        <div class="media-sub">
            <span><?= e($m['section_name'] ?? '') ?></span>
            <span><?= e(date('d M Y', strtotime((string) $m['created_at']))) ?></span>
        </div>
    </div>
</a>
