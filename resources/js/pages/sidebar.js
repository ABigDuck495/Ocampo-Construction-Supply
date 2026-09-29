/* ============================================================
   SIDEBAR BEHAVIOR
   ============================================================
    The delivery badge is rendered from the shared database-backed
    sidebar view composer. This file only handles navigation UI.
*/

document.addEventListener('DOMContentLoaded', () => {
    initDeliveryBadge();

    const navItems = document.querySelectorAll('.nav-item');

    navItems.forEach(item => {
        item.addEventListener('click', () => {
            navItems.forEach(n => n.classList.remove('active'));
            item.classList.add('active');
        });
    });

    initThemeToggle();
});

function initDeliveryBadge(){
    const badge = document.getElementById('sidebarBadge');
    const countUrl = badge?.dataset.countUrl;
    if(!badge || !countUrl) return;

    let requestActive = false;

    async function refreshCount(){
        if(requestActive || document.visibilityState === 'hidden') return;
        requestActive = true;

        try {
            const response = await fetch(countUrl, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
            });
            if(!response.ok) return;

            const data = await response.json();
            const count = Math.max(0, Number(data.count) || 0);
            badge.textContent = count > 0 ? String(count) : '';
            badge.hidden = count === 0;
        } catch (error) {
            console.error('Could not refresh delivery count:', error);
        } finally {
            requestActive = false;
        }
    }

    refreshCount();
    window.setInterval(refreshCount, 15000);
    window.addEventListener('focus', refreshCount);
    document.addEventListener('visibilitychange', () => {
        if(document.visibilityState === 'visible') refreshCount();
    });
}

/* ---------------- THEME TOGGLE ---------------- */
const SUN_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg>';
const MOON_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>';

function initThemeToggle(){
    const toggleBtn = document.getElementById('themeToggle');
    const icon = document.getElementById('themeIcon');
    const label = document.getElementById('themeLabel');
    if(!toggleBtn) return;

    const saved = localStorage.getItem('theme');
    if(saved === 'light') applyTheme(true);
    document.documentElement.classList.remove('light-mode-pending');

    toggleBtn.addEventListener('click', () => {
        const isLight = document.body.classList.contains('light-mode');
        applyTheme(!isLight);
        localStorage.setItem('theme', !isLight ? 'light' : 'dark');
    });

    function applyTheme(light){
        document.body.classList.toggle('light-mode', light);
        if (icon) icon.innerHTML = light ? SUN_ICON : MOON_ICON;
        if (label) label.textContent = light ? 'LIGHT MODE' : 'DARK MODE';
    }
}