// ==========================================================
// DASHBOARD — live clock + count-up numbers
// ==========================================================

document.addEventListener('DOMContentLoaded', () => {
    initClock();
    initCountUp();
    initRevenueRefresh();
});

// ---------- Live clock ----------
function initClock() {
    const clockEl = document.getElementById('dashClock');
    const dateEl = document.getElementById('dashDate');
    if (!clockEl || !dateEl) return;

    const tick = () => {
        const now = new Date();
        clockEl.textContent = now.toLocaleTimeString('en-PH', {
            hour: '2-digit',
            minute: '2-digit',
        });
        dateEl.textContent = now.toLocaleDateString('en-PH', {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric',
        });
    };

    tick();
    setInterval(tick, 1000);
}

// ---------- Count-up animation ----------
function initCountUp() {
    const els = document.querySelectorAll('[data-count]');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    els.forEach((el) => {
        const target = parseFloat(el.dataset.count) || 0;
        const decimals = parseInt(el.dataset.decimals || '0', 10);
        const format = (n) =>
            n.toLocaleString('en-US', {
                minimumFractionDigits: decimals,
                maximumFractionDigits: decimals,
            });

        if (reduceMotion || target === 0) {
            el.textContent = format(target);
            return;
        }

        const duration = 900;
        const start = performance.now();

        const step = (now) => {
            const t = Math.min((now - start) / duration, 1);
            const eased = 1 - Math.pow(1 - t, 3);
            el.textContent = format(target * eased);
            if (t < 1) requestAnimationFrame(step);
        };

        requestAnimationFrame(step);
    });
}

function initRevenueRefresh() {
    const revenueEl = document.querySelector('[data-live-revenue]');
    const endpoint = revenueEl?.dataset.revenueUrl;
    if (!revenueEl || !endpoint) return;

    let requestActive = false;

    async function refreshRevenue() {
        if (requestActive || document.visibilityState === 'hidden') return;
        requestActive = true;

        try {
            const response = await fetch(endpoint, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });
            if (!response.ok) return;

            const data = await response.json();
            const revenue = Number(data.todayRevenue);
            if (!Number.isFinite(revenue)) return;

            revenueEl.dataset.count = String(revenue);
            revenueEl.textContent = revenue.toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        } catch (error) {
            console.error('Could not refresh dashboard revenue:', error);
        } finally {
            requestActive = false;
        }
    }

    window.setInterval(refreshRevenue, 15000);
    window.addEventListener('focus', refreshRevenue);
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') refreshRevenue();
    });
}