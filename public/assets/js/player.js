/* Greyshades - secure HTML5 video player with HLS.js fallback to MP4 */
(() => {
    'use strict';
    const video = document.getElementById('gs-video');
    if (!video) return;

    const hlsSrc = video.dataset.hlsSrc;
    const mp4Src = video.dataset.mp4Src;

    const startNative = () => {
        if (mp4Src) video.src = mp4Src;
    };

    if (hlsSrc) {
        if (video.canPlayType('application/vnd.apple.mpegurl')) {
            // Safari / native HLS
            video.src = hlsSrc;
        } else if (window.Hls && window.Hls.isSupported()) {
            const hls = new window.Hls({ enableWorker: true, lowLatencyMode: false, capLevelToPlayerSize: true });
            hls.loadSource(hlsSrc);
            hls.attachMedia(video);
            hls.on(window.Hls.Events.ERROR, (_, data) => {
                if (data.fatal) {
                    console.warn('[player] HLS fatal, falling back to MP4', data);
                    hls.destroy();
                    startNative();
                }
            });
        } else {
            startNative();
        }
    } else {
        startNative();
    }

    // Disable native context menu on the video itself
    video.addEventListener('contextmenu', (e) => e.preventDefault());

    // Track watch progress (best-effort).
    let lastReport = 0;
    video.addEventListener('timeupdate', () => {
        if (!video.duration || video.duration === Infinity) return;
        const pct = Math.floor((video.currentTime / video.duration) * 100);
        if (pct - lastReport >= 25) {
            lastReport = pct;
            // Phase 2: POST /api/watch-progress
        }
    });
})();
