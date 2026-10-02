// ============================================================
// INVENTORY / PRODUCT CATALOG
// ============================================================

import { fuzzySearch } from '../fuzzySearch.js';
import { toast } from './toast.js';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const LOW_STOCK_THRESHOLD = 20;

const CATEGORY_ICONS = {
    Tools: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
    'Power Tools': '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    Plumbing: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h4v6H6z"/><path d="M8 9v4a4 4 0 0 0 4 4h2"/><path d="M14 15h6v6h-6z"/></svg>',
    Fasteners: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M4.2 6.2l2.1 2.1M17.7 15.7l2.1 2.1M3 12h3M18 12h3M4.2 17.8l2.1-2.1M17.7 8.3l2.1-2.1"/></svg>',
    Electrical: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h7l-1 8 10-12h-7z"/></svg>',
    Paint: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a7 7 0 0 0 7-7c0-3-3-5-3-9a4 4 0 0 0-8 0c0 4-3 6-3 9a7 7 0 0 0 7 7z"/></svg>',
    Safety: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5.5 3.5 9 8 11 4.5-2 8-5.5 8-11V5l-8-3z"/></svg>',
};

// Escapes quotes too, so names like  4" Metal Box  don't break HTML attributes
function escapeHtml(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function mapInventoryToProducts(inventories) {
    return (inventories || []).map(inv => ({
        inventoryId: inv.InventoryID,
        productId: inv.ProductID,
        name: inv.product ? inv.product.Product_Name : '(unknown product)',
        sku: inv.product ? inv.product.SKU : '',
        unit: inv.product ? inv.product.Unit : '',
        category: inv.product ? inv.product.Category : 'Tools',
        subCategory: inv.product ? inv.product.SubCategory : '',
        price: inv.product ? ((inv.product.Price === null || inv.product.Pricing_type === 'Variable') ? 'Variable' : Number(inv.product.Price)) : 0,
        stock: Number(inv.QuantityOnHand),
        reorderLevel: Number(inv.ReorderLevel),
    }));
}

const state = {
    products: mapInventoryToProducts(window.INVENTORY_DATA && window.INVENTORY_DATA.inventories),
    archived: null,        // loaded the first time ARCHIVED is clicked
    view: 'active',        // 'active' | 'archived'
    category: 'all',
    search: '',
};

function fmtMoney(n) {
    return (n === 'Variable')
        ? 'Variable'
        : '₱' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function stockClass(stock) {
    if (stock <= 0) return 'out';
    if (stock < LOW_STOCK_THRESHOLD) return 'low';
    return '';
}

function renderStats() {
    const total = state.products.length;
    const value = state.products.reduce((sum, p) => sum + (typeof p.price === 'number' ? p.price * p.stock : 0), 0);
    const low = state.products.filter(p => p.stock > 0 && p.stock < LOW_STOCK_THRESHOLD).length;

    document.getElementById('statTotal').textContent = total;
    document.getElementById('statValue').textContent = fmtMoney(value);
    document.getElementById('statLow').textContent = low;
    document.getElementById('headerSub').textContent = `${total} product${total === 1 ? '' : 's'} registered`;
}

function getFiltered() {
    const source = state.view === 'archived' ? (state.archived || []) : state.products;
    const categoryProducts = state.category === 'all'
        ? source
        : source.filter(product => product.category === state.category);

    return fuzzySearch(categoryProducts, state.search, ['name', 'sku', 'category', 'subCategory']);
}

function renderTable() {
    const body = document.getElementById('productBody');
    const rows = getFiltered();

    if (!rows.length) {
        body.innerHTML = `<tr class="empty-row"><td colspan="7">${state.view === 'archived' ? 'No archived products.' : 'No products match your search.'}</td></tr>`;
        return;
    }

    body.innerHTML = rows.map(p => {
        const icon = CATEGORY_ICONS[p.category] || CATEGORY_ICONS.Tools;
        const sCls = stockClass(p.stock);
        return `
            <tr data-inventory-id="${p.inventoryId}">
                <td>
                    <div class="prod-cell">${icon}<span>${escapeHtml(p.name)}</span></div>
                </td>
                <td class="sku-cell">${escapeHtml(p.sku)}</td>
                <td><span class="cat-pill">${icon}${escapeHtml(p.category)}</span></td>
                <td class="unit-cell">${escapeHtml(p.unit)}</td>
                <td class="price-cell">${fmtMoney(p.price)}</td>
                <td><span class="stock-pill ${sCls}">${p.stock}</span></td>
                <td>
                    <div class="actions-cell">
                        ${state.view === 'archived'
                            ? `<button class="btn-restore" data-product-id="${p.productId}" data-name="${escapeHtml(p.name)}">RESTORE</button>`
                            : `<button class="btn-edit"
                                    data-inventory-id="${p.inventoryId}"
                                    data-product-id="${p.productId}"
                                    data-name="${escapeHtml(p.name)}"
                                    data-sku="${escapeHtml(p.sku)}"
                                    data-category="${escapeHtml(p.category)}"
                                    data-subcategory="${escapeHtml(p.subCategory)}"
                                    data-price="${p.price}"
                                    data-stock="${p.stock}"
                                    data-reorder-level="${p.reorderLevel}"
                                >EDIT</button>
                                <button class="btn-del" data-product-id="${p.productId}" data-name="${escapeHtml(p.name)}">ARCHIVE</button>`}
                    </div>
                </td>
            </tr>`;
    }).join('');
}

function render() {
    renderStats();
    renderTable();
}

function updateViewUi() {
    const archBtn = document.getElementById('archivedBtn');
    const addBtn = document.getElementById('addProductBtn');
    const archived = state.view === 'archived';

    if (archBtn) {
        archBtn.textContent = archived ? '← BACK TO ACTIVE' : 'ARCHIVED';
        archBtn.classList.toggle('active', archived);
    }
    if (addBtn) addBtn.style.display = archived ? 'none' : '';

    const n = state.archived ? state.archived.length : 0;
    document.getElementById('headerSub').textContent = archived
        ? `${n} archived product${n === 1 ? '' : 's'}`
        : `${state.products.length} product${state.products.length === 1 ? '' : 's'} registered`;
}

function bindEvents() {
    document.getElementById('searchInput').addEventListener('input', (e) => {
        state.search = e.target.value;
        renderTable();
    });

    document.getElementById('catTabs').addEventListener('click', (e) => {
        const tab = e.target.closest('.tab');
        if (!tab) return;
        document.querySelectorAll('#catTabs .tab').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        state.category = tab.dataset.cat;
        renderTable();
    });

    // Build category tabs dynamically from the current inventory
    (function buildCategoryTabs(){
        const tabs = document.getElementById('catTabs');
        if (!tabs) return;
        const preferredOrder = Object.keys(CATEGORY_ICONS);
        const categories = Array.from(new Set(state.products.map(p => p.category))).filter(Boolean);
        categories.sort((a,b) => {
            const ia = preferredOrder.indexOf(a);
            const ib = preferredOrder.indexOf(b);
            if (ia !== -1 || ib !== -1) return (ia === -1 ? 999 : ia) - (ib === -1 ? 999 : ib);
            return a.localeCompare(b);
        });
        tabs.innerHTML = `<div class="tab active" data-cat="all">ALL</div>` + categories.map(cat => `\n<div class="tab" data-cat="${escapeHtml(cat)}">${escapeHtml(cat.toUpperCase())}</div>`).join('');
    })();

    // ARCHIVE / RESTORE buttons in the table.
    // (EDIT clicks are handled by inventory-edit-product.js on this same container.)
    document.getElementById('productBody').addEventListener('click', async (e) => {
        const table = document.querySelector('.product-table');

        const restoreBtn = e.target.closest('.btn-restore');
        if (restoreBtn) {
            restoreBtn.disabled = true;
            try {
                const res = await fetch(table.dataset.restoreUrlTemplate.replace('__ID__', restoreBtn.dataset.productId), {
                    method: 'PATCH',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                });
                if (!res.ok) throw new Error('Request failed');

                const idx = state.archived.findIndex(p => String(p.productId) === restoreBtn.dataset.productId);
                if (idx !== -1) state.products.unshift(state.archived.splice(idx, 1)[0]);
                render();
                updateViewUi();
                toast('Product restored.', 'success');
            } catch (err) {
                console.error('Restore failed:', err);
                toast('Could not restore the product.', 'error');
                restoreBtn.disabled = false;
            }
            return;
        }

        const btn = e.target.closest('.btn-del');
        if (!btn) return;

        if (!confirm(`Archive "${btn.dataset.name}"?\n\nIt will be hidden from POS and Inventory. Past orders and reports keep it.`)) return;

        btn.disabled = true;
        try {
            const res = await fetch(table.dataset.archiveUrlTemplate.replace('__ID__', btn.dataset.productId), {
                method: 'PATCH',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            });
            if (!res.ok) throw new Error('Request failed');

            state.products = state.products.filter(p => String(p.productId) !== btn.dataset.productId);
            state.archived = null;   // reload the archived list next time it's opened
            render();
            toast('Product archived.', 'success');
        } catch (err) {
            console.error('Archive failed:', err);
            toast('Could not archive the product.', 'error');
            btn.disabled = false;
        }
    });

    // ARCHIVED / BACK TO ACTIVE toggle
    document.getElementById('archivedBtn').addEventListener('click', async (e) => {
        const btn = e.currentTarget;

        if (state.view === 'archived') {
            state.view = 'active';
        } else {
            btn.disabled = true;
            try {
                if (state.archived === null) {
                    const res = await fetch(btn.dataset.archivedUrl, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) throw new Error('Request failed');
                    state.archived = mapInventoryToProducts(await res.json());
                }
                state.view = 'archived';
            } catch (err) {
                console.error('Load archived failed:', err);
                toast('Could not load archived products.', 'error');
                btn.disabled = false;
                return;
            }
            btn.disabled = false;
        }

        updateViewUi();
        renderTable();
    });

    document.getElementById('addProductBtn').addEventListener('click', () => {
        // Hook up your add-product modal here.
        console.log('Add product clicked');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    bindEvents();
    render();
});