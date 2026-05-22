<?php
/**
 * @var array $media
 * @var array $section
 * @var array $categories
 * @var string $streamToken
 * @var bool $canDownload
 * @var bool $canEdit
 * @var bool $canDelete
 */
use App\Core\Csrf;
$this->extend('layouts/app');
$type = $media['media_type'];
$streamUrl = url('/stream/' . $media['uuid'] . '?token=' . $streamToken);
$hlsUrl    = !empty($media['hls_master']) ? url('/stream/' . $media['uuid'] . '/hls/master.m3u8?token=' . $streamToken) : '';
$previewUrl = url('/preview/' . $media['uuid']);
?>
<div class="media-detail no-select">
    <a class="back-link" href="javascript:history.back()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back
    </a>

    <div class="media-stage glass" id="media-stage" data-type="<?= e($type) ?>">
        <?php if ($type === 'video'): ?>
            <video id="gs-video"
                   class="gs-player"
                   controls
                   controlsList="nodownload noremoteplayback"
                   disablepictureinpicture
                   playsinline
                   preload="metadata"
                   poster="<?= url('/thumb/' . $media['uuid']) ?>"
                   <?= $hlsUrl ? 'data-hls-src="'.e($hlsUrl).'"' : '' ?>
                   data-mp4-src="<?= e($streamUrl) ?>"></video>

        <?php elseif ($type === 'image'): ?>
            <div class="image-stage">
                <img src="<?= e($streamUrl) ?>" alt="<?= e($media['title']) ?>">
            </div>

        <?php elseif ($type === 'pdf'): ?>
            <div class="pdf-stage">
                <object data="<?= e($streamUrl) ?>#toolbar=1&navpanes=0" type="application/pdf">
                    <embed src="<?= e($streamUrl) ?>#toolbar=1&navpanes=0" type="application/pdf">
                    <p style="padding:2rem;text-align:center;">
                        Your browser cannot display this PDF inline.
                        <a href="<?= e($streamUrl) ?>" target="_blank" rel="noopener">Open in a new tab</a>.
                    </p>
                </object>
            </div>

        <?php elseif ($type === 'ppt'): ?>
            <div class="pdf-stage">
                <?php if (!empty($media['preview_path'])): ?>
                <object data="<?= e($previewUrl) ?>#toolbar=1&navpanes=0" type="application/pdf">
                    <embed src="<?= e($previewUrl) ?>#toolbar=1&navpanes=0" type="application/pdf">
                    <p style="padding:2rem;text-align:center;">
                        Your browser cannot display this presentation inline.
                        <a href="<?= e($previewUrl) ?>" target="_blank" rel="noopener">Open in a new tab</a>.
                    </p>
                </object>
                <?php else: ?>
                <p class="muted" style="padding:2rem;text-align:center;">Preview not available for this presentation.</p>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <p class="muted">This media type cannot be previewed inline.</p>
        <?php endif; ?>
    </div>

    <aside class="media-info glass">
        <h1><?= e($media['title']) ?></h1>
        <div class="info-meta">
            <span class="badge"><?= strtoupper($type) ?></span>
            <span class="badge soft"><?= e($section['name']) ?></span>
            <?php if (!empty($media['duration_sec'])): ?>
                <span class="badge soft"><?= e(format_duration($media['duration_sec'])) ?></span>
            <?php endif; ?>
            <span class="badge soft"><?= e(format_bytes((int) $media['file_size'])) ?></span>
            <span class="muted"><?= e(date('d M Y', strtotime((string) $media['created_at']))) ?></span>
        </div>

        <?php if (!empty($media['description'])): ?>
            <p class="info-desc"><?= nl2br(e($media['description'])) ?></p>
        <?php endif; ?>

        <?php if ($categories): ?>
        <section>
            <h3>Categories</h3>
            <div class="chip-row">
                <?php foreach ($categories as $c): ?>
                <a class="chip" href="<?= url('/dashboard?category=' . (int)$c['id']) ?>"><?= e($c['name']) ?></a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <div class="info-actions">
            <?php /* Download button only shows when 'Allow Download' was
                   checked at upload time. SuperAdmins can still hit the
                   /download/{uuid} route directly if needed for moderation,
                   but the button stays hidden so it never misleads users. */ ?>
            <?php if (!empty($media['is_downloadable']) && $canDownload): ?>
                <a class="btn-primary" href="<?= url('/download/' . $media['uuid']) ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12l7 7 7-7"/></svg>
                    Download
                </a>
            <?php endif; ?>

            <?php if ($canEdit): ?>
                <a class="btn-ghost" href="<?= url('/media/' . $media['id'] . '/edit') ?>">Edit</a>
            <?php endif; ?>

            <?php if ($canDelete): ?>
            <form method="post" action="<?= url('/media/' . $media['id'] . '/delete') ?>" onsubmit="return confirm('Delete this media permanently? This cannot be undone.')">
                <?= Csrf::field() ?>
                <button type="submit" class="btn-danger">Delete</button>
            </form>
            <?php endif; ?>
        </div>
    </aside>
</div>

<?php $this->section('scripts'); ?>
<?php if ($type === 'video'): ?>
<script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.13/dist/hls.min.js" defer></script>
<script src="<?= asset('js/player.js') ?>" defer></script>
<?php endif; ?>
<?php $this->endSection(); ?>
