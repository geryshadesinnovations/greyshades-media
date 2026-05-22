/* Greyshades — topbar search autocomplete.
 *
 * Hits GET /search/suggest?q=... after a short debounce and renders the
 * returned matches (media titles, tags, categories, occasions) in a
 * dropdown under the search input. Selecting a suggestion navigates to
 * the item's href; pressing Enter without picking one falls back to the
 * normal text search through the dashboard.
 */
(() => {
    'use strict';

    const form  = document.querySelector('form.topbar-search[data-search-suggest]');
    if (!form) return;
    const input = form.querySelector('input[name="q"]');
    const list  = form.querySelector('.search-suggest');
    if (!input || !list) return;

    let abortCtl = null;
    let debounceId = 0;
    let activeIdx = -1;
    let lastItems = [];

    const escapeHtml = (s) => String(s ?? '').replace(/[&<>"']/g, c =>
        ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

    /** Build the suggest URL relative to the form's action so the app base path is honoured. */
    const suggestUrl = (q) => {
        const base = (form.getAttribute('action') || '/dashboard').replace(/\/dashboard\/?$/, '');
        return base + '/search/suggest?q=' + encodeURIComponent(q);
    };

    const close = () => {
        list.hidden = true;
        list.innerHTML = '';
        activeIdx = -1;
        lastItems = [];
        input.setAttribute('aria-expanded', 'false');
    };

    const render = (items, q) => {
        if (!items.length) {
            list.innerHTML = '<li class="search-suggest-empty">No matches for &ldquo;' + escapeHtml(q) + '&rdquo;</li>';
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            activeIdx = -1;
            lastItems = [];
            return;
        }

        const iconFor = (type) => {
            switch (type) {
                case 'media':    return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 16l5-5 4 4 4-3 5 4"/></svg>';
                case 'category': return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/></svg>';
                case 'company':  return '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>';
                default:         return '';
            }
        };

        list.innerHTML = items.map((it, i) => {
            // Show match count beside the suggestion. We always render it so
            // the user can see at a glance whether a category/company has any
            // media (count = 0 means "nothing here yet"). For media-title hits
            // the count represents the total number of files matching the
            // query across the user's allowed sections.
            const count = (typeof it.count === 'number') ? it.count : 0;
            const countHtml = '<span class="search-suggest-count" data-empty="' + (count === 0 ? '1' : '0') + '">' + count + '</span>';
            return '<li class="search-suggest-item" role="option" data-idx="' + i + '" data-href="' + escapeHtml(it.href) + '">' +
                '<span class="search-suggest-icon">' + iconFor(it.type) + '</span>' +
                '<span class="search-suggest-label">' + escapeHtml(it.label) + '</span>' +
                countHtml +
                (it.meta ? '<span class="search-suggest-meta">' + escapeHtml(it.meta) + '</span>' : '') +
            '</li>';
        }).join('');
        list.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        activeIdx = -1;
        lastItems = items;
    };

    const fetchSuggestions = async (q) => {
        if (abortCtl) abortCtl.abort();
        abortCtl = new AbortController();
        try {
            const res = await fetch(suggestUrl(q), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                signal: abortCtl.signal,
            });
            if (!res.ok) return;
            const data = await res.json();
            // Make sure the input value didn't change while we waited.
            if (input.value.trim() !== q) return;
            render(data.items || [], q);
        } catch (e) {
            if (e.name !== 'AbortError') close();
        }
    };

    input.addEventListener('input', () => {
        const q = input.value.trim();
        clearTimeout(debounceId);
        if (q.length < 2) { close(); return; }
        debounceId = setTimeout(() => fetchSuggestions(q), 150);
    });

    input.addEventListener('focus', () => {
        const q = input.value.trim();
        if (q.length >= 2 && lastItems.length) { list.hidden = false; input.setAttribute('aria-expanded', 'true'); }
    });

    /* Keyboard navigation */
    input.addEventListener('keydown', (e) => {
        if (list.hidden || !lastItems.length) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            const dir = e.key === 'ArrowDown' ? 1 : -1;
            activeIdx = (activeIdx + dir + lastItems.length) % lastItems.length;
            list.querySelectorAll('.search-suggest-item').forEach((li, i) => {
                li.classList.toggle('active', i === activeIdx);
                if (i === activeIdx) li.scrollIntoView({ block: 'nearest' });
            });
        } else if (e.key === 'Enter' && activeIdx >= 0 && lastItems[activeIdx]) {
            e.preventDefault();
            window.location.href = lastItems[activeIdx].href;
        } else if (e.key === 'Escape') {
            close();
            input.blur();
        }
    });

    /* Click-to-pick */
    list.addEventListener('click', (e) => {
        const li = e.target.closest('.search-suggest-item');
        if (!li) return;
        const href = li.getAttribute('data-href');
        if (href) window.location.href = href;
    });

    /* Close on outside click */
    document.addEventListener('click', (e) => {
        if (!form.contains(e.target)) close();
    });
})();
