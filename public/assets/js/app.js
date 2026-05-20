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
})();
