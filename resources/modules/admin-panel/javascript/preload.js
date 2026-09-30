(function () {
    'use strict';
    const root = document.documentElement;
    const read = (key) => { try { return localStorage.getItem(key); } catch (_) { return null; } };
    const savedTheme = read('gf-theme');
    const systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
    root.dataset.bsTheme = savedTheme === 'dark' || savedTheme === 'light' ? savedTheme : (systemDark ? 'dark' : 'light');
    root.style.colorScheme = root.dataset.bsTheme;
    root.dataset.gfSidebar = read('gf-sidebar') === 'collapsed' ? 'collapsed' : 'expanded';
})();
