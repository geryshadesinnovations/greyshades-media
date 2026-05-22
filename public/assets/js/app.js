/* Greyshades - global UI behaviour: theme toggle, popovers, sidebar tree, mobile nav */
(() => {
    'use strict';

    // Theme toggle
    const root = document.documentElement;
    const themeBtn = document.getElementById('theme-toggle');
    const setTheme = (t) => {
        root.setAttribute('data-theme', t);
        document.cookie = 'theme=' + t + '; path=/; max-age=' + (60 * 60 * 24 * 365) + '; SameSite=Lax';
    };
    if (themeBtn) {
        themeBtn.addEventListener('click', () => {
            const next = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            setTheme(next);
        });
    }

    // Generic popovers (data-popover="<id>")
    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-popover]');
        if (trigger) {
            const id = trigger.getAttribute('data-popover');
            const pop = document.getElementById(id);
            if (pop) {
                document.querySelectorAll('.popover').forEach(p => { if (p !== pop) p.hidden = true; });
                pop.hidden = !pop.hidden;
                e.stopPropagation();
                return;
            }
        }
        if (!e.target.closest('.popover')) {
            document.querySelectorAll('.popover').forEach(p => p.hidden = true);
        }
    });

    // Sidebar tree expand/collapse
    document.querySelectorAll('[data-toggle="tree"]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            const li = btn.closest('.tree-item');
            const kids = li.querySelector('.tree-children');
            if (!kids) return;
            const open = !kids.hasAttribute('hidden');
            if (open) kids.setAttribute('hidden', ''); else kids.removeAttribute('hidden');
            btn.classList.toggle('open', !open);
        });
    });
    // Auto-open paths that contain the active link
    document.querySelectorAll('.tree-link.active').forEach(link => {
        let p = link.closest('.tree-item');
        while (p) {
            const kids = p.querySelector(':scope > .tree-children');
            if (kids) kids.removeAttribute('hidden');
            const t = p.querySelector(':scope > .tree-row > .tree-toggle');
            if (t) t.classList.add('open');
            p = p.parentElement?.closest('.tree-item');
        }
    });

    // Mobile sidebar
    const sidebar = document.querySelector('.sidebar');
    const sidebarToggle = document.getElementById('sidebar-toggle');
    if (sidebar && sidebarToggle) {
        sidebarToggle.addEventListener('click', () => sidebar.classList.toggle('open'));
    }

    // Auto-dismiss toasts
    document.querySelectorAll('.toast').forEach(t => setTimeout(() => t.remove(), 4000));

    // Generic accordion cards [data-accordion]
    document.querySelectorAll('[data-accordion]').forEach(btn => {
        btn.addEventListener('click', () => {
            const body = btn.nextElementSibling;
            if (!body) return;
            const isOpen = body.classList.contains('open');
            body.classList.toggle('open', !isOpen);
            btn.classList.toggle('open', !isOpen);
        });
    });

    // Unregister any previously installed service worker (offline feature removed).
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(regs => {
            regs.forEach(r => r.unregister());
        }).catch(() => {});
    }

    // Video card hover-to-play preview
    (() => {
        const isMobile = 'ontouchstart' in window;
        let activeCard = null;
        let activeVideo = null;

        function startPreview(card) {
            const src = card.dataset.previewSrc;
            if (!src) return;
            stopPreview();
            const thumb = card.querySelector('.media-thumb');
            if (!thumb) return;
            const video = document.createElement('video');
            video.src = src;
            video.muted = true;
            video.loop = true;
            video.playsInline = true;
            video.autoplay = true;
            video.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:inherit;z-index:2;';
            thumb.style.position = 'relative';
            thumb.appendChild(video);
            video.play().catch(() => {});
            activeCard = card;
            activeVideo = video;
        }

        function stopPreview() {
            if (activeVideo) {
                activeVideo.pause();
                activeVideo.remove();
                activeVideo = null;
            }
            activeCard = null;
        }

        if (!isMobile) {
            document.addEventListener('mouseenter', (e) => {
                const card = e.target.closest('.media-card[data-preview-src]');
                if (card) startPreview(card);
            }, true);
            document.addEventListener('mouseleave', (e) => {
                const card = e.target.closest('.media-card[data-preview-src]');
                if (card && card === activeCard) stopPreview();
            }, true);
        } else {
            // Mobile: play on touchstart if card is in viewport
            document.addEventListener('touchstart', (e) => {
                const card = e.target.closest('.media-card[data-preview-src]');
                if (card && card !== activeCard) {
                    const rect = card.getBoundingClientRect();
                    if (rect.top >= 0 && rect.bottom <= window.innerHeight) {
                        startPreview(card);
                    }
                } else if (!card) {
                    stopPreview();
                }
            }, {passive: true});
        }
    })();
})();
