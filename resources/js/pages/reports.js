/* ============================================================
   REPORTS - DATA + LOGIC
   Filtered by Month/Year + Report Type (orders | items) selectors.
   Header stat bar (top orange strip) has its OWN month/year picker,
   independent of the Sales Summary filter below it, so you can glance
   at any past month's totals without clicking Generate Report or
   exporting a file. Defaults to the current month on page load.
   ============================================================ */

let salesStats = { totalRevenue: 0, totalOrders: 0, avgOrderValue: 0, topCategory: '—' };
let topProducts = [];
let recentSales = [];
let itemsOrdered = [];
let deliveryHistory = [];

function fmt(n){ return '$' + Number(n || 0).toFixed(2); }

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
                <span class="tp-name">${p.name}</span>
                <span class="tp-value">${p.unitsSold} sold</span>
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
            <td class="cell-dim">${s.id}</td>
            <td>${s.customer}</td>
            <td class="cell-dim">${s.items} item${s.items > 1 ? 's' : ''}</td>
            <td class="cell-total">${fmt(s.total)}</td>
            <td><span class="badge payment-${s.payment.toLowerCase().replace(/\s+/g,'-')}">${s.payment}</span> <span class="badge status-${s.paymentStatus.toLowerCase()}">${s.paymentStatus}</span></td>
            <td class="cell-dim">${s.date}</td>
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
            <td>${i.name}</td>
            <td class="cell-dim">${i.category}</td>
            <td class="cell-dim">${i.unitsSold}</td>
            <td class="cell-total">${fmt(i.revenue)}</td>
        </tr>`).join('');
}

/* ---------------- RENDER: DELIVERY HISTORY ---------------- */
function badgeForDeliveryStatus(status){
    const map = { transit:'TRANSIT', delivered:'DELIVERED', returned:'RETURNED' };
    return `<span class="badge ${status}">${map[status] || status.toUpperCase()}</span>`;
}

function renderDeliveryHistory(){
    const body = document.getElementById('deliveryHistoryBody');
    if(!deliveryHistory.length){
        body.innerHTML = `<tr><td colspan="7" class="empty-state">NO DELIVERY RECORDS</td></tr>`;
        return;
    }
    body.innerHTML = deliveryHistory.map(d => `
        <tr>
            <td class="cell-dim">${d.id}</td>
            <td>${d.customer}</td>
            <td class="cell-dim">${d.truck} &middot; ${d.driver}</td>
            <td>${badgeForDeliveryStatus(d.status)}</td>
            <td class="cell-dim">${d.dispatched}</td>
            <td class="cell-dim">${d.delivered}</td>
            <td><span class="badge payment-${d.payment.toLowerCase().replace(/\s+/g,'-')}">${d.payment}</span></td>
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

/* ---------------- FETCH: FILTERED REPORT DATA (Sales Summary section) ---------------- */
async function fetchReportsData(){
    try {
        const res = await fetch(`/reports/data?${currentFilterParams()}`);
        if(!res.ok) throw new Error('Failed to load report data');
        const data = await res.json();

        salesStats = data.salesStats;
        topProducts = data.topProducts;
        recentSales = data.recentSales;
        itemsOrdered = data.itemsOrdered || [];
        deliveryHistory = data.deliveryHistory;

        renderSalesStats();
        renderTopProducts();
        renderRecentSales();
        renderItemsOrdered();
        renderDeliveryHistory();
        toggleReportTypePanels();
    } catch (err) {
        console.error(err);
    }
}

/* ---------------- FETCH: HEADER SUMMARY (own month/year picker) ---------------- */
function currentHeaderParams(){
    return new URLSearchParams({
        month: document.getElementById('headerMonth').value,
        year: document.getElementById('headerYear').value,
    });
}

async function fetchHeaderSummary(){
    try {
        const res = await fetch(`/reports/summary?${currentHeaderParams()}`);
        if(!res.ok) throw new Error('Failed to load summary');
        const data = await res.json();

        document.getElementById('statTotalRevenue').textContent = fmt(data.totalRevenue);
        document.getElementById('statTotalOrders').textContent = data.totalOrders;
        document.getElementById('statAvgOrder').textContent = fmt(data.avgOrderValue);
    } catch (err) {
        console.error(err);
    }
}

/* ---------------- EXPORTS (Sales Summary section) ---------------- */
function buildExportUrl(base){
    return `${base}?${currentFilterParams()}`;
}

document.getElementById('btnGenerateReport').addEventListener('click', fetchReportsData);
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
        fetchReportsData();
    });
}

/* Header picker: changing month or year re-fetches the summary immediately,
   AND syncs the Sales Summary filter below to the same month/year, so the
   stat cards, top products, and recent sales table all reflect the same
   period — one picker driving the whole dashboard, no button needed. */
const headerMonthEl = document.getElementById('headerMonth');
const headerYearEl = document.getElementById('headerYear');
if(headerMonthEl && headerYearEl){
    headerMonthEl.addEventListener('change', () => {
        document.getElementById('filterMonth').value = headerMonthEl.value;
        document.getElementById('filterYear').value = headerYearEl.value;
        fetchHeaderSummary();
        fetchReportsData();
    });
    headerYearEl.addEventListener('change', () => {
        document.getElementById('filterMonth').value = headerMonthEl.value;
        document.getElementById('filterYear').value = headerYearEl.value;
        fetchHeaderSummary();
        fetchReportsData();
    });
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
        headerStatsEl.style.display = (target === 'delivery') ? 'none' : '';
    }
});

/* ---------------- INIT ---------------- */
const today = new Date();

// Sales Summary filter (unchanged behavior)
populateYearOptions(document.getElementById('filterYear'));
document.getElementById('filterMonth').value = today.getMonth() + 1;
document.getElementById('filterYear').value = today.getFullYear();

// Header picker (new) — defaults to current month/year on load
if(headerMonthEl && headerYearEl){
    populateMonthOptions(headerMonthEl);
    populateYearOptions(headerYearEl);
    headerMonthEl.value = today.getMonth() + 1;
    headerYearEl.value = today.getFullYear();
}

toggleReportTypePanels();
fetchReportsData();
fetchHeaderSummary();