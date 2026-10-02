/* ============================================================
   REPORTS - DATA + LOGIC (LIVE)
   Filtered by Month/Year + Report Type (orders | items) selectors.
   Header stat bar (top orange strip) has its OWN month/year picker,
   independent of the Sales Summary filter below it, so you can glance
   at any past month's totals without clicking Generate Report or
   exporting a file. Defaults to the current month on page load.

   LIVE UPDATES
   - The page re-requests /reports/data and /reports/summary every
     POLL_INTERVAL_MS while the tab is visible, and immediately when
     the tab becomes visible again.
   - Polling always uses the filters from the LAST Generate/change, so
     a half-changed dropdown never silently swaps the data.
   - Only one poll runs at a time; a user action (Generate, month/year,
     report type) cancels an in-flight poll so stale data never wins.
   - The DOM is only re-rendered when the payload actually changed, and
     expanded daily-summary cards stay expanded across refreshes.
   - Other pages in the same browser can trigger an instant refresh with:
         new BroadcastChannel('ocampo-data').postMessage('changed');
   ============================================================ */
import { initPaymentStatus } from './payment-status.js';

const POLL_INTERVAL_MS = 8000;

let salesStats = { totalRevenue: 0, totalOrders: 0, avgOrderValue: 0, topCategory: '—' };
let topProducts = [];
let recentSales = [];
let itemsOrdered = [];
let deliveryHistory = [];
let reportSummaries = [];


// live-refresh state
let appliedParams = null;          // query string used for the Sales Summary fetch
let reportsAbort = null;           // AbortController of the in-flight /reports/data request
let headerAbort = null;            // AbortController of the in-flight /reports/summary request
let lastReportsSig = '';
let lastHeaderSig = '';
let openReportKeys = new Set();    // dates of expanded daily summary cards
let openStateInitialized = false;

function fmt(n){ return '₱' + Number(n || 0).toFixed(2); }

function esc(v){
    return String(v ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));
}

function slug(v){
    return String(v ?? '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
}

const FETCH_OPTS = {
    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    cache: 'no-store',
};

/* ---------------- RENDER: DAILY SUMMARIES ---------------- */
function renderReportSummaries(){
    const list = document.getElementById('reportSummaryList');
    if (!list) return;

    if (!reportSummaries.length) {
        list.innerHTML = '<div class="empty-state-box">No daily summaries yet</div>';
        return;
    }

    // First render for a given filter: open the newest card. After that,
    // keep whatever the user has expanded.
    if (!openStateInitialized) {
        openReportKeys = new Set([String(reportSummaries[0].date)]);
        openStateInitialized = true;
    }

    list.innerHTML = reportSummaries.map((report, index) => {
        const isOpen = openReportKeys.has(String(report.date));
        return `
        <div class="report-summary-card ${isOpen ? 'open' : ''}" data-report-key="${esc(report.date)}">
            <button type="button" class="report-summary-header" data-report-index="${index}">
                <div>
                    <div class="report-date">${esc(report.date)}</div>
                    <div class="report-meta">Generated ${esc(report.generatedAt)}</div>
                </div>
                <div class="report-kpis">
                    <span><strong>${esc(report.totalOrders)}</strong> Orders</span>
                    <span><strong>${fmt(report.totalSales)}</strong> Sales</span>
                    <span><strong>${esc(report.totalDeliveries)}</strong> Delivered</span>
                </div>
                <span class="report-toggle-icon">${isOpen ? '−' : '+'}</span>
            </button>
            <div class="report-summary-body">
                <div class="summary-grid">
                    <div class="summary-tile"><div class="summary-tile-label">Revenue</div><div class="summary-tile-value orange">${fmt(report.totalSales)}</div></div>
                    <div class="summary-tile"><div class="summary-tile-label">Orders</div><div class="summary-tile-value">${esc(report.totalOrders)}</div></div>
                    <div class="summary-tile"><div class="summary-tile-label">Items Sold</div><div class="summary-tile-value blue">${esc(report.totalItemsSold)}</div></div>
                    <div class="summary-tile"><div class="summary-tile-label">Deliveries</div><div class="summary-tile-value green">${esc(report.totalDeliveries)}</div></div>
                    <div class="summary-tile"><div class="summary-tile-label">Dispatches</div><div class="summary-tile-value">${esc(report.totalDispatches ?? 0)}</div></div>
                    <div class="summary-tile"><div class="summary-tile-label">Low Stock</div><div class="summary-tile-value">${esc(report.lowStockItemCount ?? 0)}</div></div>
                </div>
                <div class="detail-list">
                    <div class="detail-panel">
                        <h4>Dispatches</h4>
                        <div class="detail-items">
                            ${report.dispatches && report.dispatches.length ? report.dispatches.map(d => `
                                <div class="detail-item"><span>#${esc(d.DispatchID)} · ${esc(d.Status)}</span><span>${d.DispatchDate ? esc(new Date(d.DispatchDate).toLocaleDateString()) : '—'}</span></div>
                            `).join('') : '<div class="detail-item"><span>No dispatches</span><span>—</span></div>'}
                        </div>
                    </div>
                    <div class="detail-panel">
                        <h4>Deliveries</h4>
                        <div class="detail-items">
                            ${report.deliveries && report.deliveries.length ? report.deliveries.map(d => `
                                <div class="detail-item"><span>#${esc(d.DeliveryID)} · ${esc(d.Status)}</span><span>${d.DeliveryDate ? esc(new Date(d.DeliveryDate).toLocaleDateString()) : '—'}</span></div>
                            `).join('') : '<div class="detail-item"><span>No deliveries</span><span>—</span></div>'}
                        </div>
                    </div>
                </div>
            </div>
        </div>`;
    }).join('');
}

/* One delegated click handler (survives re-renders) — accordion: one open at a time */
function bindSummaryToggle(){
    const list = document.getElementById('reportSummaryList');
    if (!list) return;

    list.addEventListener('click', (e) => {
        const button = e.target.closest('.report-summary-header');
        if (!button) return;

        const card = button.closest('.report-summary-card');
        const key = card.dataset.reportKey;
        const wasOpen = card.classList.contains('open');

        list.querySelectorAll('.report-summary-card').forEach(item => {
            item.classList.remove('open');
            const icon = item.querySelector('.report-toggle-icon');
            if (icon) icon.textContent = '+';
        });
        openReportKeys.clear();

        if (!wasOpen) {
            card.classList.add('open');
            const icon = card.querySelector('.report-toggle-icon');
            if (icon) icon.textContent = '−';
            openReportKeys.add(key);
        }
    });
}

/* ---------------- RENDER: SALES SUMMARY (filtered stat cards) ---------------- */
function renderSalesStats(){
    document.getElementById('cardRevenue').textContent = fmt(salesStats.totalRevenue);
    document.getElementById('cardOrders').textContent = salesStats.totalOrders;
    document.getElementById('cardAvg').textContent = fmt(salesStats.avgOrderValue);
    document.getElementById('cardCategory').textContent = salesStats.topCategory || '—';
}

function renderTopProducts(){
    const wrap = document.getElementById('topProductsList');
    if(!topProducts.length){
        wrap.innerHTML = `<div class="empty-state">NO PRODUCT DATA</div>`;
        return;
    }
    const segCount = 10;
    wrap.innerHTML = topProducts.map(p => {
        const filled = Math.round((p.percent / 100) * segCount);
        return `
        <div class="top-product-row">
            <div class="tp-top">
                <span class="tp-name">${esc(p.name)}</span>
                <span class="tp-value">${esc(p.unitsSold)} sold</span>
            </div>
            <div class="tp-bar">
                ${Array.from({length: segCount}).map((_, i) => `<div class="tp-seg ${i < filled ? 'filled' : ''}"></div>`).join('')}
            </div>
        </div>`;
    }).join('');
}

function renderRecentSales(){
    const body = document.getElementById('recentSalesBody');
    if(!recentSales.length){
        body.innerHTML = `<tr><td colspan="6" class="empty-state">NO SALES RECORDED</td></tr>`;
        return;
    }
    body.innerHTML = recentSales.map(s => `
        <tr>
            <td class="cell-dim">${esc(s.id)}</td>
            <td>${esc(s.customer)}</td>
            <td class="cell-dim">${esc(s.items)} item${s.items > 1 ? 's' : ''}</td>
            <td class="cell-total">${fmt(s.total)}</td>
            <td><span class="badge payment-${slug(s.payment)}">${esc(s.payment)}</span> <span class="badge status-${slug(s.paymentStatus)}">${esc(s.paymentStatus)}</span></td>
            <td class="cell-dim">${esc(s.date)}</td>
        </tr>`).join('');
}

/* ---------------- RENDER: ITEMS ORDERED ---------------- */
function renderItemsOrdered(){
    const body = document.getElementById('itemsOrderedBody');
    if(!body) return; // panel not present in DOM yet
    if(!itemsOrdered.length){
        body.innerHTML = `<tr><td colspan="4" class="empty-state">NO ITEMS RECORDED</td></tr>`;
        return;
    }
    body.innerHTML = itemsOrdered.map(i => `
        <tr>
            <td>${esc(i.name)}</td>
            <td class="cell-dim">${esc(i.category)}</td>
            <td class="cell-dim">${esc(i.unitsSold)}</td>
            <td class="cell-total">${fmt(i.revenue)}</td>
        </tr>`).join('');
}

/* ---------------- RENDER: DELIVERY HISTORY ---------------- */
function badgeForDeliveryStatus(status){
    const map = { transit:'TRANSIT', delivered:'DELIVERED', returned:'RETURNED' };
    const key = slug(status);
    const label = map[key] || (String(status ?? '').trim() ? String(status).toUpperCase() : '—');
    return `<span class="badge ${key}">${esc(label)}</span>`;
}

function renderDeliveryHistory(){
    const body = document.getElementById('deliveryHistoryBody');
    if(!deliveryHistory.length){
        body.innerHTML = `<tr><td colspan="7" class="empty-state">NO DELIVERY RECORDS</td></tr>`;
        return;
    }
    body.innerHTML = deliveryHistory.map(d => `
        <tr>
            <td class="cell-dim">${esc(d.id)}</td>
            <td>${esc(d.customer)}</td>
            <td class="cell-dim">${esc(d.truck)} &middot; ${esc(d.driver)}</td>
            <td>${badgeForDeliveryStatus(d.status)}</td>
            <td class="cell-dim">${esc(d.dispatched)}</td>
            <td class="cell-dim">${esc(d.delivered)}</td>
            <td>${d.payment ? `<span class="badge payment-${slug(d.payment)}">${esc(d.payment)}</span>` : '—'}</td>
        </tr>`).join('');
}

/* ---------------- FILTER STATE HELPERS (Sales Summary section) ---------------- */
function currentReportType(){
    const el = document.getElementById('filterReportType');
    return el ? el.value : 'orders';
}

function currentFilterParams(){
    return new URLSearchParams({
        month: document.getElementById('filterMonth').value,
        year: document.getElementById('filterYear').value,
        type: currentReportType(),
    });
}

function populateYearOptions(selectEl){
    const currentYear = new Date().getFullYear();
    const startYear = currentYear - 5;
    for (let y = currentYear; y >= startYear; y--) {
        const opt = document.createElement('option');
        opt.value = y;
        opt.textContent = y;
        selectEl.appendChild(opt);
    }
}

const MONTH_NAMES = ['January','February','March','April','May','June','July','August','September','October','November','December'];

function populateMonthOptions(selectEl){
    MONTH_NAMES.forEach((name, i) => {
        const opt = document.createElement('option');
        opt.value = i + 1;
        opt.textContent = name;
        selectEl.appendChild(opt);
    });
}

/* ---------------- PANEL TOGGLE (Customer Orders vs Items Ordered) ---------------- */
function toggleReportTypePanels(){
    const type = currentReportType();
    const ordersPanel = document.getElementById('panel-recentSales');
    const itemsPanel = document.getElementById('panel-itemsOrdered');
    if(!ordersPanel || !itemsPanel) return;

    if(type === 'items'){
        ordersPanel.style.display = 'none';
        itemsPanel.style.display = '';
    } else {
        ordersPanel.style.display = '';
        itemsPanel.style.display = 'none';
    }
}

/* ---------------- FETCH: FILTERED REPORT DATA (Sales Summary section) ----------------
   force = true  -> user action: cancel any in-flight request and fetch now
   force = false -> poll: skipped if a request is already running            */
async function fetchReportsData({ force = false } = {}){
    if (reportsAbort && !force) return;
    if (reportsAbort) reportsAbort.abort();

    if (!appliedParams) appliedParams = currentFilterParams().toString();

    const ctrl = new AbortController();
    reportsAbort = ctrl;

    try {
        const res = await fetch(`/reports/data?${appliedParams}`, { ...FETCH_OPTS, signal: ctrl.signal });
        if(!res.ok) throw new Error('Failed to load report data');
        const data = await res.json();

        if (ctrl !== reportsAbort) return; // superseded by a newer request

        const sig = JSON.stringify(data);
        if (sig === lastReportsSig) return; // nothing changed — leave the DOM alone
        lastReportsSig = sig;

        salesStats = data.salesStats;
        topProducts = data.topProducts || [];
        recentSales = data.recentSales || [];
        itemsOrdered = data.itemsOrdered || [];
        deliveryHistory = data.deliveryHistory || [];
        reportSummaries = data.reportSummaries || [];

        renderSalesStats();
        renderTopProducts();
        renderRecentSales();
        renderItemsOrdered();
        renderDeliveryHistory();
        renderReportSummaries();
        toggleReportTypePanels();
    } catch (err) {
        if (err.name !== 'AbortError') console.error(err);
    } finally {
        if (reportsAbort === ctrl) reportsAbort = null;
    }
}

/* Apply the filter dropdowns as the new "current period" and load it now. */
function applyFiltersAndFetch(){
    appliedParams = currentFilterParams().toString();
    lastReportsSig = '';
    openStateInitialized = false; // new period -> open the newest daily card again
    return fetchReportsData({ force: true });
}

/* ---------------- FETCH: HEADER SUMMARY (own month/year picker) ---------------- */
function currentHeaderParams(){
    return new URLSearchParams({
        month: document.getElementById('headerMonth').value,
        year: document.getElementById('headerYear').value,
    });
}

async function fetchHeaderSummary({ force = false } = {}){
    if (headerAbort && !force) return;
    if (headerAbort) headerAbort.abort();

    const ctrl = new AbortController();
    headerAbort = ctrl;

    try {
        const res = await fetch(`/reports/summary?${currentHeaderParams()}`, { ...FETCH_OPTS, signal: ctrl.signal });
        if(!res.ok) throw new Error('Failed to load summary');
        const data = await res.json();

        if (ctrl !== headerAbort) return;

        const sig = JSON.stringify(data);
        if (sig === lastHeaderSig) return;
        lastHeaderSig = sig;

        document.getElementById('statTotalRevenue').textContent = fmt(data.totalRevenue);
        document.getElementById('statTotalOrders').textContent = data.totalOrders;
        document.getElementById('statAvgOrder').textContent = fmt(data.avgOrderValue);
    } catch (err) {
        if (err.name !== 'AbortError') console.error(err);
    } finally {
        if (headerAbort === ctrl) headerAbort = null;
    }
}

/* ---------------- LIVE POLLING ---------------- */
function pollOnce(){
    if (document.hidden) return;
    fetchReportsData();
    fetchHeaderSummary();
}

function startPolling(){
    setInterval(pollOnce, POLL_INTERVAL_MS);

    // catch up immediately when the user comes back to this tab
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) pollOnce();
    });

    // instant refresh when another tab/page in this browser announces a change
    if ('BroadcastChannel' in window) {
        const channel = new BroadcastChannel('ocampo-data');
        channel.addEventListener('message', pollOnce);
    }
}

/* ---------------- EXPORTS (Sales Summary section) ---------------- */
function buildExportUrl(base){
    return `${base}?${currentFilterParams()}`;
}

document.getElementById('btnGenerateReport').addEventListener('click', applyFiltersAndFetch);
document.getElementById('btnExportPdf').addEventListener('click', () => {
    window.location.href = buildExportUrl('/reports/export/pdf');
});
document.getElementById('btnExportCsv').addEventListener('click', () => {
    window.location.href = buildExportUrl('/reports/export/csv');
});

const filterReportTypeEl = document.getElementById('filterReportType');
if(filterReportTypeEl){
    filterReportTypeEl.addEventListener('change', () => {
        toggleReportTypePanels();
        applyFiltersAndFetch();
    });
}

/* Header picker: changing month or year re-fetches the summary immediately,
   AND syncs the Sales Summary filter below to the same month/year, so the
   stat cards, top products, and recent sales table all reflect the same
   period — one picker driving the whole dashboard, no button needed. */
const headerMonthEl = document.getElementById('headerMonth');
const headerYearEl = document.getElementById('headerYear');

function onHeaderPickerChange(){
    document.getElementById('filterMonth').value = headerMonthEl.value;
    document.getElementById('filterYear').value = headerYearEl.value;
    lastHeaderSig = '';
    fetchHeaderSummary({ force: true });
    applyFiltersAndFetch();
}

if(headerMonthEl && headerYearEl){
    headerMonthEl.addEventListener('change', onHeaderPickerChange);
    headerYearEl.addEventListener('change', onHeaderPickerChange);
}

/* ---------------- TABS ---------------- */
document.getElementById('reportTabs').addEventListener('click', e => {
    const tab = e.target.closest('.tab');
    if(!tab) return;
    const target = tab.dataset.tab;

    document.querySelectorAll('#reportTabs .tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');

    document.querySelectorAll('.report-view').forEach(v => v.classList.remove('active'));
    document.getElementById(`view-${target}`).classList.add('active');

    // Revenue/Orders/Avg Order stats + month picker only make sense for
    // Sales Summary — hide them on Delivery History.
        const headerStatsEl = document.getElementById('headerStats');
    if(headerStatsEl){
        headerStatsEl.style.display = (target === 'delivery' || target === 'payments') ? 'none' : '';
    }

    if (target === 'payments') initPaymentStatus();
});

/* ---------------- INIT ---------------- */
const today = new Date();

// Sales Summary filter (unchanged behavior)
populateYearOptions(document.getElementById('filterYear'));
document.getElementById('filterMonth').value = today.getMonth() + 1;
document.getElementById('filterYear').value = today.getFullYear();

// Header picker — defaults to current month/year on load
if(headerMonthEl && headerYearEl){
    populateMonthOptions(headerMonthEl);
    populateYearOptions(headerYearEl);
    headerMonthEl.value = today.getMonth() + 1;
    headerYearEl.value = today.getFullYear();
}

bindSummaryToggle();
toggleReportTypePanels();
applyFiltersAndFetch();
fetchHeaderSummary({ force: true });
startPolling();