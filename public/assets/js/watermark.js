/* Greyshades - dynamic watermark overlay.
 * Renders a continuously-moving, semi-transparent grid of "user · email · session · time"
 * across the whole viewport so any screen recording or capture carries traceable info. */
(() => {
    'use strict';
    const wm = document.getElementById('gs-watermark');
    if (!wm) return;

    const user    = wm.dataset.user || '';
    const email   = wm.dataset.email || '';
    const session = wm.dataset.session || '';

    const buildSvg = (offset = 0) => {
        const ts   = new Date().toISOString().replace('T',' ').slice(0,19);
        const text = (user + ' · ' + email + ' · ' + session + ' · ' + ts).replace(/&/g,'&amp;').replace(/</g,'&lt;');
        const tile = 360, fontSize = 14;
        const svg =
          `<svg xmlns="http://www.w3.org/2000/svg" width="${tile}" height="${tile}" viewBox="0 0 ${tile} ${tile}">` +
            `<g transform="rotate(-22 ${tile/2} ${tile/2})" font-family="Inter, sans-serif" font-size="${fontSize}" font-weight="600" fill="currentColor">` +
              `<text x="${20 + offset}" y="40">${text}</text>` +
              `<text x="${20 - offset/2}" y="160">${text}</text>` +
              `<text x="${20 + offset}" y="280">${text}</text>` +
            `</g>` +
          `</svg>`;
        return 'url("data:image/svg+xml;utf8,' + encodeURIComponent(svg) + '")';
    };

    const apply = (offset) => {
        wm.style.backgroundImage = buildSvg(offset);
        wm.style.backgroundRepeat = 'repeat';
    };

    apply(0);
    let off = 0;
    setInterval(() => { off = (off + 4) % 60; apply(off); }, 1500);

    // If someone tries to hide us via DOM, restore.
    new MutationObserver(() => {
        if (wm.style.display === 'none' || wm.hidden) {
            wm.hidden = false; wm.style.display = '';
        }
    }).observe(wm, { attributes: true });
})();
