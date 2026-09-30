(function () {
    'use strict';
    const root = document.documentElement;
    const body = document.body;
    const sidebar = document.getElementById('sidebar');
    if (!sidebar) return;

    const mobile = window.matchMedia('(max-width: 991.98px)');
    const read = (key) => { try { return localStorage.getItem(key); } catch (_) { return null; } };
    const write = (key, value) => { try { localStorage.setItem(key, value); } catch (_) {} };
    const remove = (key) => { try { localStorage.removeItem(key); } catch (_) {} };
    let themeChosen = read('gf-theme') === 'light' || read('gf-theme') === 'dark';
    let lastFocus = null;

    function applySidebar() {
        const collapsed = root.dataset.gfSidebar === 'collapsed';
        body.classList.toggle('toggle-sidebar', collapsed);
        const button = document.querySelector('.toggle-sidebar-btn');
        if (button) {
            button.setAttribute('aria-expanded', String(!collapsed));
            button.setAttribute('aria-label', collapsed ? 'Expandir menú' : 'Contraer menú');
            const icon = button.querySelector('i');
            if (icon) icon.className = collapsed ? 'gicon-arrowff' : 'gicon-arrowrw';
        }
    }

    function closeDrawer() {
        body.classList.remove('drawer-open', 'no-scroll');
        document.getElementById('sidebarOverlay')?.remove();
        document.querySelectorAll('.js-mobile-toggle').forEach(button => button.setAttribute('aria-expanded', 'false'));
        if (lastFocus) lastFocus.focus();
        lastFocus = null;
    }

    function openDrawer(button) {
        lastFocus = button;
        body.classList.add('drawer-open', 'no-scroll');
        const overlay = document.createElement('div');
        overlay.id = 'sidebarOverlay';
        overlay.className = 'sidebar-overlay';
        overlay.addEventListener('click', closeDrawer);
        body.appendChild(overlay);
        document.querySelectorAll('.js-mobile-toggle').forEach(control => control.setAttribute('aria-expanded', 'true'));
        sidebar.querySelector('.js-mobile-toggle')?.focus();
    }

    function applyTheme(theme) {
        root.dataset.bsTheme = theme;
        root.style.colorScheme = theme;
        document.getElementById('darkmode')?.setAttribute('aria-pressed', String(theme === 'dark'));
    }

    applySidebar();
    applyTheme(root.dataset.bsTheme === 'dark' ? 'dark' : 'light');

    document.querySelector('.toggle-sidebar-btn')?.addEventListener('click', () => {
        root.dataset.gfSidebar = root.dataset.gfSidebar === 'collapsed' ? 'expanded' : 'collapsed';
        write('gf-sidebar', root.dataset.gfSidebar);
        applySidebar();
    });
    document.querySelectorAll('.js-mobile-toggle').forEach(button => button.addEventListener('click', () => {
        if (!mobile.matches) return;
        body.classList.contains('drawer-open') ? closeDrawer() : openDrawer(button);
    }));
    document.addEventListener('keydown', event => {
        if (!body.classList.contains('drawer-open')) return;
        if (event.key === 'Escape') closeDrawer();
        if (event.key !== 'Tab') return;
        const controls = [...sidebar.querySelectorAll('a[href], button:not([disabled])')].filter(el => el.getClientRects().length);
        if (!controls.length) return;
        const first = controls[0];
        const last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    });
    mobile.addEventListener?.('change', () => { if (!mobile.matches && body.classList.contains('drawer-open')) closeDrawer(); });

    document.getElementById('darkmode')?.addEventListener('click', () => {
        const theme = root.dataset.bsTheme === 'dark' ? 'light' : 'dark';
        applyTheme(theme);
        write('gf-theme', theme);
        themeChosen = true;
    });
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', event => {
        if (!themeChosen) applyTheme(event.matches ? 'dark' : 'light');
    });
    window.addEventListener('storage', event => {
        if (event.key === 'gf-sidebar') { root.dataset.gfSidebar = event.newValue === 'collapsed' ? 'collapsed' : 'expanded'; applySidebar(); }
        if (event.key === 'gf-theme') { themeChosen = event.newValue === 'dark' || event.newValue === 'light'; applyTheme(themeChosen ? event.newValue : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')); }
    });
    window.GFTheme = {
        get: () => root.dataset.bsTheme,
        set: (theme, persist = true) => { if (theme !== 'light' && theme !== 'dark') return; applyTheme(theme); if (persist) { write('gf-theme', theme); themeChosen = true; } },
        resetToSystem: () => { remove('gf-theme'); themeChosen = false; applyTheme(window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); }
    };

})();
