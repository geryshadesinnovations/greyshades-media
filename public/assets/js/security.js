/* Greyshades - browser-level deterrents.
 * IMPORTANT: these are deterrents only. They cannot guarantee 100% prevention
 * of screen recording or scraping; they raise the effort bar and ensure leaks
 * are accompanied by the user's watermark. */
(() => {
    'use strict';

    // 1. Right-click on protected media
    document.addEventListener('contextmenu', (e) => {
        const t = e.target;
        if (t && (t.tagName === 'IMG' || t.tagName === 'VIDEO' || t.closest('.media-stage') || t.closest('.no-select'))) {
            e.preventDefault();
        }
    });

    // 2. Drag-save protection on images/videos in protected areas
    document.addEventListener('dragstart', (e) => {
        const t = e.target;
        if (t && (t.tagName === 'IMG' || t.tagName === 'VIDEO')) e.preventDefault();
    });

    // 3. Block common dev-tool shortcuts (best effort; will not stop a determined user)
    document.addEventListener('keydown', (e) => {
        const k = e.key, ctrl = e.ctrlKey || e.metaKey;
        // F12
        if (k === 'F12') { e.preventDefault(); return; }
        // Ctrl+Shift+(I|J|C|K)  - open devtools / inspector / console
        if (ctrl && e.shiftKey && ['I','J','C','K'].includes(k.toUpperCase())) { e.preventDefault(); return; }
        // Ctrl+U - view source
        if (ctrl && k.toLowerCase() === 'u') { e.preventDefault(); return; }
        // Ctrl+S - save page
        if (ctrl && k.toLowerCase() === 's') { e.preventDefault(); return; }
        // Ctrl+P - print
        if (ctrl && k.toLowerCase() === 'p') { e.preventDefault(); return; }
    });

    // 4. Tab visibility hint - blur protected media when window loses focus
    const blurTargets = () => document.querySelectorAll('.media-stage, .pdf-stage, .image-stage, .ppt-stage');
    document.addEventListener('visibilitychange', () => {
        const hidden = document.visibilityState === 'hidden';
        blurTargets().forEach(el => {
            el.style.transition = 'filter .15s';
            el.style.filter = hidden ? 'blur(20px)' : '';
        });
    });

    // 5. Detect devtools open by size delta (deterrent only).
    let warned = false;
    setInterval(() => {
        const threshold = 160;
        const open = (window.outerWidth - window.innerWidth > threshold) ||
                     (window.outerHeight - window.innerHeight > threshold);
        if (open && !warned) {
            warned = true;
            console.warn('%cGreyshades Media Platform', 'color:#a78bfa;font-size:18px;font-weight:700');
            console.warn('All access to this content is logged with your username, IP and session ID.');
        }
    }, 2000);
})();
