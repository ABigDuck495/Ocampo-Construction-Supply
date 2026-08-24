/* ============================================================
   REPORTS - DATA + LOGIC
   Filtered by Month/Year + Report Type (orders | items) selectors.
   ============================================================ */

let salesStats = { totalRevenue: 0, totalOrders: 0, avgOrderValue: 0, topCategory: '—' };
let topProducts = [];
let recentSales = [];
let itemsOrdered = [];
let deliveryHistory = [];

function fmt(n){ return '$' + Number(n || 0).toFixed(2); }

/* ---------------- RENDER: SALES SUMMARY ---------------- */
function renderSalesStats(){
    document.getElementById('statTotalRevenue').textContent = fmt(salesStats.totalRevenue);
    document.getElementById('statTotalOrders').textContent = salesStats.totalOrders;
    document.getElementById('statAvgOrder').textContent = fmt(salesStats.avgOrderValue);

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
            <td>${i.productName}</td>
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

/* ---------------- FILTER STATE HELPERS ---------------- */
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

function populateYearOptions(){
    const yearSelect = document.getElementById('filterYear');
    const currentYear = new Date().getFullYear();
    const startYear = currentYear - 5;
    for (let y = currentYear; y >= startYear; y--) {
        const opt = document.createElement('option');
        opt.value = y;
        opt.textContent = y;
        yearSelect.appendChild(opt);
    }
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

/* ---------------- FETCH FROM BACKEND ---------------- */
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

/* ---------------- EXPORTS ---------------- */
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

/* ---------------- TABS ---------------- */
document.getElementById('reportTabs').addEventListener('click', e => {
    const tab = e.target.closest('.tab');
    if(!tab) return;
    const target = tab.dataset.tab;

    document.querySelectorAll('#reportTabs .tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');

    document.querySelectorAll('.report-view').forEach(v => v.classList.remove('active'));
    document.getElementById(`view-${target}`).classList.add('active');
});

/* ---------------- INIT ---------------- */
populateYearOptions();
const today = new Date();
document.getElementById('filterMonth').value = today.getMonth() + 1;
document.getElementById('filterYear').value = today.getFullYear();

toggleReportTypePanels();
fetchReportsData();