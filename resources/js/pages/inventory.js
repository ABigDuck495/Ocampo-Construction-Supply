// ============================================================
// INVENTORY / PRODUCT CATALOG
// ============================================================

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

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function mapInventoryToProducts(inventories) {
    return (inventories || []).map(inv => ({
        // used by appendProductRow-style row identity / future edit-delete wiring
        inventoryId: inv.InventoryID,
        productId: inv.ProductID,
        name: inv.product ? inv.product.Product_Name : '(unknown product)',
        sku: inv.product ? inv.product.SKU : '',
        category: inv.product ? inv.product.Category : 'Tools',
        subCategory: inv.product ? inv.product.SubCategory : '',
        price: inv.product ? Number(inv.product.Price) : 0,
        stock: Number(inv.QuantityOnHand),
        reorderLevel: Number(inv.ReorderLevel),
    }));
}

const state = {
    products: mapInventoryToProducts(window.INVENTORY_DATA && window.INVENTORY_DATA.inventories),
    category: 'all',
    search: '',
};

function fmtMoney(n) {
    return '$' + Number(n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function stockClass(stock) {
    if (stock <= 0) return 'out';
    if (stock < LOW_STOCK_THRESHOLD) return 'low';
    return '';
}

function renderStats() {
    const total = state.products.length;
    const value = state.products.reduce((sum, p) => sum + p.price * p.stock, 0);
    const low = state.products.filter(p => p.stock > 0 && p.stock < LOW_STOCK_THRESHOLD).length;
    const out = state.products.filter(p => p.stock <= 0).length;

    document.getElementById('statTotal').textContent = total;
    document.getElementById('statValue').textContent = fmtMoney(value);
    document.getElementById('statLow').textContent = low;
    document.getElementById('statOut').textContent = out;
    document.getElementById('headerSub').textContent = `${total} product${total === 1 ? '' : 's'} registered`;
}

function getFiltered() {
    const q = state.search.trim().toLowerCase();
    return state.products.filter(p => {
        const matchesCat = state.category === 'all' || p.category === state.category;
        const matchesSearch = !q ||
            p.name.toLowerCase().includes(q) ||
            p.sku.toLowerCase().includes(q) ||
            p.category.toLowerCase().includes(q);
        return matchesCat && matchesSearch;
    });
}

function renderTable() {
    const body = document.getElementById('productBody');
    const rows = getFiltered();

    if (!rows.length) {
        body.innerHTML = `<tr class="empty-row"><td colspan="6">No products match your search.</td></tr>`;
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
                <td class="price-cell">${fmtMoney(p.price)}</td>
                <td><span class="stock-pill ${sCls}">${p.stock}</span></td>
                <td>
                    <div class="actions-cell">
                        <button class="btn-edit"
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
                        <button class="btn-del" data-inventory-id="${p.inventoryId}">DEL</button>
                    </div>
                </td>
            </tr>`;
    }).join('');
}

function render() {
    renderStats();
    renderTable();
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

    document.getElementById('productBody').addEventListener('click', (e) => {
        const delBtn = e.target.closest('.btn-del');
        if (delBtn) {
            // Hook up your delete confirmation / request here.
            console.log('Delete inventory row', delBtn.dataset.inventoryId);
        }
        // EDIT clicks are handled by inventory-edit-product.js, which
        // listens on this same #productBody container.
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