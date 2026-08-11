/* ============================================================
   REPORTS - DATA + LOGIC

   Data is expected to be injected from the backend (e.g. via a
   Blade @json() seed rendered into `window.reportsData` in
   reports.blade.php, or fetched from a Laravel controller/API
   endpoint) before this script runs. No mock data is defined
   here — populate window.reportsData server-side, or wire up
   an API call in loadReportsData() below.

   Expected shape:
   window.reportsData = {
       salesStats: { totalRevenue, totalOrders, avgOrderValue, topCategory },
       topProducts: [{ name, unitsSold, percent }, ...],
       recentSales: [{ id, customer, items, total, payment, paymentStatus, date }, ...],
       deliveryHistory: [{ id, customer, truck, driver, status, dispatched, delivered, payment }, ...],
   }
   ============================================================ */

let salesStats = { totalRevenue: 0, totalOrders: 0, avgOrderValue: 0, topCategory: '—' };
let topProducts = [];
let recentSales = [];
let deliveryHistory = [];

function loadReportsData(){
    const data = window.reportsData;
    if(!data) return;
    salesStats = data.salesStats || salesStats;
    topProducts = data.topProducts || [];
    recentSales = data.recentSales || [];
    deliveryHistory = data.deliveryHistory || [];
}

function fmt(n){ return '$' + Number(n || 0).toFixed(2); }

/* ---------------- RENDER: SALES SUMMARY ---------------- */
function renderSalesStats(){
    // header strip
    document.getElementById('statTotalRevenue').textContent = fmt(salesStats.totalRevenue);
    document.getElementById('statTotalOrders').textContent = salesStats.totalOrders;
    document.getElementById('statAvgOrder').textContent = fmt(salesStats.avgOrderValue);

    // stat cards (sales summary view)
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
loadReportsData();
renderSalesStats();
renderTopProducts();
renderRecentSales();
renderDeliveryHistory();