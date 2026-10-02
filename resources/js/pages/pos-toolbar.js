// ==========================================================
// POS TOOLBAR — product search + pinned toolbar height
// Works alongside pos.js without touching it: search hides
// already-rendered .product-card elements, and a MutationObserver
// re-applies the filter whenever pos.js re-renders the grid
// (category tab change, etc.).
// ==========================================================

import { fuzzySearch } from '../fuzzySearch.js'

document.addEventListener('DOMContentLoaded', () => {
    initToolbarHeight();
    initProductSearch();
});

// ---------- Pinned toolbar height (for the sticky cart panel) ----------
function initToolbarHeight() {
    const toolbar = document.querySelector('.page-toolbar');
    if (!toolbar) return;

    const update = () => {
        document.documentElement.style.setProperty('--toolbar-h', toolbar.offsetHeight + 'px');
    };

    update();
    window.addEventListener('resize', update);

    if ('ResizeObserver' in window) {
        new ResizeObserver(update).observe(toolbar);
    }
}

// ---------- Product search ----------
function initProductSearch() {
    const input = document.getElementById('productSearch');
    const clearBtn = document.getElementById('searchClear');
    const countEl = document.getElementById('searchCount');
    const grid = document.getElementById('productGrid');
    if (!input || !grid) return;

    const applyFilter = () => {
        const query = input.value.trim();
        const cards = Array.from(grid.querySelectorAll('.product-card'));
        const searchableCards = cards.map(card => ({ card, text: card.textContent }));
        const matches = new Set(fuzzySearch(searchableCards, query, ['text']).map(item => item.card));
        let shown = 0;

        cards.forEach((card) => {
            const match = matches.has(card);
            card.style.display = match ? '' : 'none';
            if (match) shown++;
        });

        clearBtn.hidden = query === '';

        if (!query) {
            countEl.textContent = '';
            countEl.classList.remove('empty');
        } else if (shown === 0) {
            countEl.textContent = 'NO MATCHES';
            countEl.classList.add('empty');
        } else {
            countEl.textContent = shown + (shown === 1 ? ' RESULT' : ' RESULTS');
            countEl.classList.remove('empty');
        }
    };

    input.addEventListener('input', applyFilter);

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            input.value = '';
            applyFilter();
        }
    });

    clearBtn.addEventListener('click', () => {
        input.value = '';
        applyFilter();
        input.focus();
    });

    // pos.js re-renders the grid on tab change — keep the filter applied.
    new MutationObserver(applyFilter).observe(grid, { childList: true });

    applyFilter();
}