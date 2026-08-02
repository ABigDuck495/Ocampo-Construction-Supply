/* ============================================================
   SIDEBAR BEHAVIOR
   ============================================================
   Note: the "sidebarBadge" count itself is driven by order data
   and is updated from js/deliveries.js (renderStats) since it
   reflects delivery counts. This file only handles nav UI.
*/

document.addEventListener('DOMContentLoaded', () => {
    const navItems = document.querySelectorAll('.nav-item');

    navItems.forEach(item => {
        item.addEventListener('click', () => {
            navItems.forEach(n => n.classList.remove('active'));
            item.classList.add('active');
        });
    });

    initThemeToggle();
});

/* ---------------- THEME TOGGLE ---------------- */
const SUN_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>';
const MOON_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';

function initThemeToggle(){
    const toggleBtn = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');
    const label = document.getElementById('themeLabel');
    if(!toggleBtn) return;

    const saved = localStorage.getItem('theme'); // 'light' | 'dark' | null
    if(saved === 'light') applyTheme(true);

    toggleBtn.addEventListener('click', () => {
        const isLight = document.body.classList.contains('light-mode');
        applyTheme(!isLight);
        localStorage.setItem('theme', !isLight ? 'light' : 'dark');
    });

    function applyTheme(light){
        document.body.classList.toggle('light-mode', light);
        icon.innerHTML = light ? SUN_ICON : MOON_ICON;
        label.textContent = light ? 'LIGHT MODE' : 'DARK MODE';
    }
}