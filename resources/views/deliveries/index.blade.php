<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Delivery Ops - Ironclad Hardware</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Press+Start+2P&display=swap" rel="stylesheet">

<style>
:root{
    --bg:#0a0c11;
    --panel:#11141c;
    --panel-alt:#161a24;
    --border:#262b38;
    --border-hard:#000;
    --text:#e9e7df;
    --text-dim:#7b8296;
    --text-faint:#4d5265;
    --orange:#f5a623;
    --orange-dark:#c9840f;
    --orange-glow:rgba(245,166,35,.35);
    --blue:#4d9eff;
    --blue-dark:#1c5fc4;
    --green:#3fd15a;
    --green-dark:#1f8c39;
    --red:#ff5c5c;
}

*{box-sizing:border-box;}
html,body{margin:0;padding:0;}
body{
    background:var(--bg);
    color:var(--text);
    font-family:'JetBrains Mono', ui-monospace, monospace;
    min-height:100vh;
    display:flex;
}

/* ---------- SIDEBAR ---------- */
.sidebar{
    width:220px;
    flex-shrink:0;
    background:#0d0f16;
    border-right:2px solid var(--border-hard);
    display:flex;
    flex-direction:column;
    padding:18px 14px;
}
.brand{
    display:flex;
    align-items:center;
    gap:10px;
    padding:8px 6px 20px 6px;
    border-bottom:2px solid var(--border);
    margin-bottom:16px;
}
.brand-icon{
    width:34px;height:34px;
    background:linear-gradient(160deg,#ffcf6b,var(--orange-dark));
    border:2px solid #000;
    display:flex;align-items:center;justify-content:center;
    box-shadow:0 0 0 1px #000, 0 0 14px var(--orange-glow);
}
.brand-icon svg{width:18px;height:18px;color:#1a1305;}
.brand-name{font-weight:800;font-size:12px;letter-spacing:.04em;color:var(--orange);line-height:1.3;}
.brand-sub{font-size:9px;color:var(--text-dim);letter-spacing:.05em;}

.nav{display:flex;flex-direction:column;gap:4px;}
.nav-item{
    display:flex;align-items:center;justify-content:space-between;
    gap:10px;
    padding:10px 10px;
    color:var(--text-dim);
    font-size:11px;
    font-weight:600;
    letter-spacing:.04em;
    text-decoration:none;
    border:2px solid transparent;
    cursor:pointer;
    user-select:none;
}
.nav-item svg{width:15px;height:15px;flex-shrink:0;}
.nav-item .lbl{display:flex;align-items:center;gap:10px;flex:1;}
.nav-item:hover{color:var(--text);}
.nav-item.active{
    background:var(--orange);
    color:#1a1305;
    border-color:#000;
    box-shadow:2px 2px 0 #000;
}
.nav-badge{
    background:#000;
    color:var(--orange);
    font-size:10px;
    font-weight:800;
    padding:1px 6px;
    border:1px solid #000;
}
.nav-item.active .nav-badge{background:#1a1305;color:var(--orange);}

/* ---------- MAIN ---------- */
.main{flex:1;min-width:0;padding:20px 24px 40px;}

.header{
    position:relative;
    background:var(--orange);
    background-image:radial-gradient(rgba(0,0,0,.14) 1.5px, transparent 1.5px);
    background-size:9px 9px;
    border:2px solid #000;
    box-shadow:3px 3px 0 #000;
    padding:20px 24px;
    display:flex;
    align-items:flex-start;
    justify-content:space-between;
    margin-bottom:18px;
    color:#1a1305;
}
.header h1{
    margin:0 0 6px;
    font-size:22px;
    font-weight:800;
    letter-spacing:.03em;
}
.header p{margin:0;font-size:12px;font-weight:600;opacity:.75;}
.header-stats{display:flex;gap:22px;}
.hstat{text-align:center;}
.hstat b{display:block;font-size:22px;font-weight:800;color:#fff;text-shadow:1px 1px 0 #000;}
.hstat span{font-size:9px;font-weight:700;letter-spacing:.05em;opacity:.8;}

.tabs{display:flex;gap:6px;margin-bottom:14px;flex-wrap:wrap;}
.tab{
    background:var(--panel);
    border:2px solid var(--border);
    color:var(--text-dim);
    font-size:10.5px;
    font-weight:700;
    letter-spacing:.04em;
    padding:8px 14px;
    cursor:pointer;
    user-select:none;
}
.tab.active{background:#fff;color:#111;border-color:#000;box-shadow:2px 2px 0 #000;}
.hint{font-size:10px;color:var(--text-faint);letter-spacing:.08em;margin-bottom:12px;font-weight:600;text-transform:uppercase;}

.board{display:grid;grid-template-columns:340px 1fr;gap:18px;align-items:start;}

/* ---------- ORDER LIST ---------- */
.order-list{display:flex;flex-direction:column;gap:12px;}
.order-card{
    background:var(--panel);
    border:2px solid var(--border);
    padding:14px;
    cursor:grab;
    position:relative;
}
.order-card[draggable="true"]:active{cursor:grabbing;}
.order-card.dragging{opacity:.4;}
.order-card.is-assigned{border-color:var(--orange-dark);}
.oc-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;}
.oc-id{font-size:11px;color:var(--text-dim);display:flex;align-items:center;gap:8px;}
.oc-id .grip{color:var(--text-faint);letter-spacing:2px;}
.badge{
    font-size:9px;font-weight:800;letter-spacing:.05em;
    padding:2px 7px;border:1px solid currentColor;
}
.badge.pending{color:var(--orange);}
.badge.transit{color:var(--blue);}
.badge.assigned{color:#c98bff;}
.badge.delivered{color:var(--green);}
.badge.returned{color:var(--red);}
.badge.zone-north{color:var(--blue);}
.badge.zone-south{color:var(--orange);}

.oc-name{font-size:15px;font-weight:800;margin:2px 0 8px;}
.oc-addr{font-size:10.5px;color:var(--text-dim);display:flex;align-items:center;gap:6px;margin-bottom:10px;}
.oc-addr svg{width:12px;height:12px;flex-shrink:0;}
.oc-items{background:var(--panel-alt);border:1px solid var(--border);padding:8px 10px;margin-bottom:10px;}
.oc-item-row{display:flex;justify-content:space-between;font-size:11px;padding:2px 0;color:var(--text);}
.oc-item-row span.qty{color:var(--text-dim);}
.oc-bottom{display:flex;align-items:center;justify-content:space-between;}
.oc-price{color:var(--orange);font-weight:800;font-size:14px;}
.oc-actions{display:flex;align-items:center;gap:8px;}
.btn-ghost{
    background:transparent;border:1px solid var(--border);color:var(--text-dim);
    font-size:9.5px;font-weight:700;padding:5px 9px;letter-spacing:.04em;cursor:pointer;
}
.btn-ghost:hover{color:var(--text);border-color:var(--text-dim);}
.drag-hint{font-size:9px;color:var(--text-faint);letter-spacing:.04em;}
.oc-assigned-tag{font-size:9.5px;color:var(--orange-dark);font-weight:700;margin-top:8px;}

/* ---------- TRUCK FLEET ---------- */
.fleet-panel{background:transparent;}
.fleet-head{
    display:flex;align-items:center;justify-content:space-between;
    padding:12px 4px 14px;
    border-bottom:2px solid var(--border);
    margin-bottom:14px;
}
.fleet-title{font-size:13px;font-weight:800;letter-spacing:.06em;color:var(--text);}
.fleet-stats{display:flex;gap:18px;}
.fstat{text-align:center;}
.fstat b{display:block;font-size:15px;font-weight:800;}
.fstat span{font-size:8.5px;color:var(--text-dim);letter-spacing:.04em;}
.fstat.idle b{color:var(--text-dim);}
.fstat.loading b{color:var(--orange);}
.fstat.transit b{color:var(--blue);}
.fstat.delivered b{color:var(--green);}

.truck-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
@media (max-width: 900px){.truck-grid{grid-template-columns:1fr;} .board{grid-template-columns:1fr;}}

.truck-card{
    background:var(--panel);
    border:2px solid var(--border);
    padding:14px;
    display:flex;flex-direction:column;gap:10px;
    transition:border-color .15s ease;
}
.truck-card.drop-active{border-color:var(--orange);box-shadow:0 0 0 2px var(--orange-glow);}
.tc-top{display:flex;align-items:center;justify-content:space-between;}
.tc-name{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:800;}
.tc-name svg{width:15px;height:15px;color:var(--text-dim);}
.tc-driver{font-size:10.5px;color:var(--text-dim);margin-top:1px;}

.cargo-label{display:flex;justify-content:space-between;font-size:9.5px;font-weight:700;letter-spacing:.04em;color:var(--text-dim);margin-bottom:4px;}
.cargo-label b{color:var(--orange);}
.cargo-bar{display:flex;gap:2px;height:14px;}
.cargo-seg{flex:1;background:var(--panel-alt);border:1px solid var(--border);}
.cargo-seg.filled{background:var(--green);border-color:#000;}

.tc-orders{display:flex;flex-direction:column;gap:8px;min-height:10px;}
.tc-order{
    background:var(--panel-alt);
    border:1px solid var(--border);
    padding:8px 10px;
}
.tc-order-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;}
.tc-order-id{font-size:10.5px;color:var(--text-dim);}
.tc-order-name{font-size:12px;font-weight:700;margin-bottom:4px;}
.tc-order-items{font-size:10px;color:var(--text-dim);}
.tc-order-items div{display:flex;justify-content:space-between;padding:1px 0;}
.icon-btn{
    background:transparent;border:1px solid var(--border);color:var(--text-dim);
    width:20px;height:20px;display:flex;align-items:center;justify-content:center;cursor:pointer;
}
.icon-btn:hover{color:var(--text);border-color:var(--text-dim);}
.icon-btn svg{width:11px;height:11px;}
.icon-btn.danger:hover{color:var(--red);border-color:var(--red);}

.dropzone-empty{
    border:1.5px dashed var(--border);
    text-align:center;
    padding:14px 8px;
    font-size:9.5px;
    color:var(--text-faint);
    letter-spacing:.04em;
}

.tc-actions{display:flex;gap:8px;}
.btn{
    flex:1;
    border:2px solid #000;
    font-weight:800;
    font-size:11.5px;
    letter-spacing:.03em;
    padding:10px;
    display:flex;align-items:center;justify-content:center;gap:8px;
    cursor:pointer;
    box-shadow:2px 2px 0 #000;
    transition:transform .1s ease, filter .1s ease;
}
.btn:hover{filter:brightness(1.08);}
.btn:active{transform:translate(2px,2px);box-shadow:0 0 0 #000;}
.btn svg{width:13px;height:13px;}
.btn-dispatch{background:var(--blue);color:#fff;}
.btn-delivered{background:var(--green);color:#08210d;}
.btn-return{background:transparent;color:var(--red);border-color:var(--red);box-shadow:2px 2px 0 var(--red);flex:0 0 auto;padding:10px 12px;}
.btn-cancel{background:var(--panel-alt);color:var(--text-dim);flex:0 0 auto;padding:10px 12px;}

.tc-departed{font-size:9px;color:var(--text-faint);text-align:center;letter-spacing:.04em;}

.empty-state{font-size:11px;color:var(--text-faint);text-align:center;padding:30px 0;letter-spacing:.03em;}
</style>
</head>
<body>

<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
        </div>
        <div>
            <div class="brand-name">IRONCLAD</div>
            <div class="brand-sub">Hardware POS</div>
        </div>
    </div>
    <nav class="nav">
        <a class="nav-item"><span class="lbl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="14" rx="1"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg>
            POS</span></a>
        <a class="nav-item active"><span class="lbl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v10H3z"/><path d="M14 10h4l3 3v4h-7z"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="17.5" cy="19" r="1.5"/></svg>
            DELIVERY</span><span class="nav-badge" id="sidebarBadge">5</span></a>
        <a class="nav-item"><span class="lbl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
            REPORTS</span></a>
        <a class="nav-item"><span class="lbl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17.5" cy="9" r="2.5"/><path d="M15 20a5 5 0 0 1 8 0"/></svg>
            USERS</span></a>
    </nav>
</aside>

<main class="main">
    <div class="header">
        <div>
            <h1>DELIVERY OPS</h1>
            <p id="headerSub">5 orders &middot; 3 trucks</p>
        </div>
        <div class="header-stats">
            <div class="hstat"><b id="statPending">3</b><span>PENDING</span></div>
            <div class="hstat"><b id="statTransit">2</b><span>TRANSIT</span></div>
            <div class="hstat"><b id="statDone">0</b><span>DONE</span></div>
        </div>
    </div>

    <div class="tabs" id="tabs">
        <div class="tab active" data-tab="all">ALL (<span class="cnt-all">5</span>)</div>
        <div class="tab" data-tab="pending">PENDING (<span class="cnt-pending">3</span>)</div>
        <div class="tab" data-tab="transit">TRANSIT (<span class="cnt-transit">2</span>)</div>
        <div class="tab" data-tab="assigned">PARTIAL (<span class="cnt-assigned">0</span>)</div>
        <div class="tab" data-tab="delivered">DELIVERED (<span class="cnt-delivered">0</span>)</div>
    </div>
    <div class="hint">&middot; Drag orders onto trucks &middot;</div>

    <div class="board">
        <div class="order-list" id="orderList"></div>

        <div class="fleet-panel">
            <div class="fleet-head">
                <div class="fleet-title">TRUCK FLEET</div>
                <div class="fleet-stats">
                    <div class="fstat idle"><b id="fIdle">1</b><span>IDLE</span></div>
                    <div class="fstat loading"><b id="fLoading">1</b><span>LOADING</span></div>
                    <div class="fstat transit"><b id="fTransit">1</b><span>TRANSIT</span></div>
                    <div class="fstat delivered"><b id="fDelivered">0</b><span>DELIVERED</span></div>
                </div>
            </div>
            <div class="truck-grid" id="truckGrid"></div>
        </div>
    </div>
</main>

<script>
/* ---------------- DATA ---------------- */
let orders = [
    { id:'RC-041823', customer:'Juan dela Cruz', address:'123 Magsaysay St, Brgy. San Jose', zone:'North',
      items:[{name:'Titanium Hammer',qty:2},{name:'Hex Bolt M8 x50',qty:5}], total:85.58, status:'transit', truck:'T1' },
    { id:'RC-041891', customer:'Maria Santos', address:'45 Rizal Ave, Brgy. Poblacion', zone:'North',
      items:[{name:'PVC Pipe 3/4"',qty:5},{name:'Circuit Breaker 20A',qty:2}], total:52.08, status:'pending', truck:null },
    { id:'RC-041956', customer:'Pedro Reyes', address:'789 Luna St, Brgy. Sta. Cruz', zone:'South',
      items:[{name:'Cordless Drill 18V',qty:1},{name:'Safety Gloves L',qty:2}], total:64.30, status:'transit', truck:'T2' },
    { id:'RC-042004', customer:'Ana Lopez', address:'12 Bonifacio St, Brgy. San Jose', zone:'North',
      items:[{name:'Paint Roller Set',qty:3},{name:'Latex Paint 1L',qty:4}], total:47.15, status:'pending', truck:null },
    { id:'RC-042017', customer:'Carlo Dizon', address:'56 Mabini St, Brgy. Sta. Cruz', zone:'South',
      items:[{name:'Steel Nails 1kg',qty:6}], total:18.90, status:'pending', truck:null },
];

let trucks = [
    { id:'T1', name:'Ironclad 01', driver:'Ben Santos', plate:'ABC-1234', capacity:30, status:'loading', departed:null },
    { id:'T2', name:'Ironclad 02', driver:'Mia Cruz', plate:'DEF-5678', capacity:25, status:'transit', departed:'Jul 23, 2026, 9:10 AM' },
    { id:'T3', name:'Ironclad 03', driver:'Jake Reyes', plate:'GHI-9012', capacity:20, status:'idle', departed:null },
];

let activeTab = 'all';
let dragOrderId = null;

const svg = {
    pin:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-7.2-7-12a7 7 0 0 1 14 0c0 4.8-7 12-7 12z"/><circle cx="12" cy="9" r="2.5"/></svg>',
    truck:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v10H3z"/><path d="M14 10h4l3 3v4h-7z"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="17.5" cy="19" r="1.5"/></svg>',
    eye:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
    x:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>',
    arrow:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 5l7 7-7 7"/></svg>',
    check:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>',
    back:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14l-4-4 4-4"/><path d="M5 10h11a4 4 0 0 1 0 8h-1"/></svg>',
};

function fmt(n){ return '$' + n.toFixed(2); }
function cargoOf(order){ return order.items.reduce((s,i)=>s+i.qty,0); }
function truckCargo(truck){
    return orders.filter(o=>o.truck===truck.id).reduce((s,o)=>s+cargoOf(o),0);
}

function render(){
    renderStats();
    renderOrderList();
    renderTrucks();
}

function renderStats(){
    const pending = orders.filter(o=>o.status==='pending').length;
    const transit = orders.filter(o=>o.status==='transit').length;
    const assigned = orders.filter(o=>o.status==='assigned').length;
    const delivered = orders.filter(o=>o.status==='delivered').length;
    const returned = orders.filter(o=>o.status==='returned').length;

    document.getElementById('statPending').textContent = pending;
    document.getElementById('statTransit').textContent = transit;
    document.getElementById('statDone').textContent = delivered;
    document.getElementById('headerSub').textContent = `${orders.length} orders · ${trucks.length} trucks`;
    document.getElementById('sidebarBadge').textContent = pending + assigned + transit;

    document.querySelector('.cnt-all').textContent = orders.length;
    document.querySelector('.cnt-pending').textContent = pending;
    document.querySelector('.cnt-transit').textContent = transit;
    document.querySelector('.cnt-assigned').textContent = assigned;
    document.querySelector('.cnt-delivered').textContent = delivered;

    document.getElementById('fIdle').textContent = trucks.filter(t=>t.status==='idle').length;
    document.getElementById('fLoading').textContent = trucks.filter(t=>t.status==='loading').length;
    document.getElementById('fTransit').textContent = trucks.filter(t=>t.status==='transit').length;
    document.getElementById('fDelivered').textContent = trucks.filter(t=>t.status==='delivered').length;
}

function badgeFor(status){
    const map = {pending:'PENDING', transit:'TRANSIT', assigned:'PARTIAL', delivered:'DELIVERED', returned:'RETURNED'};
    return `<span class="badge ${status}">${map[status]}</span>`;
}

function renderOrderList(){
    const list = document.getElementById('orderList');
    const filtered = orders.filter(o => activeTab==='all' ? true : o.status===activeTab);

    if(!filtered.length){
        list.innerHTML = `<div class="empty-state">NO ORDERS IN THIS VIEW</div>`;
        return;
    }

    list.innerHTML = filtered.map(o => {
        const truck = trucks.find(t=>t.id===o.truck);
        const draggable = (o.status==='pending' || o.status==='assigned');
        return `
        <div class="order-card ${o.status==='assigned'?'is-assigned':''}" data-order="${o.id}" ${draggable?'draggable="true"':''}>
            <div class="oc-top">
                <div class="oc-id"><span class="grip">${draggable?'::':''}</span>${o.id}</div>
                ${badgeFor(o.status)}
            </div>
            <div class="oc-name">${o.customer}</div>
            <div class="oc-addr">${svg.pin}${o.address}&nbsp;&nbsp;<span class="badge zone-${o.zone.toLowerCase()}">${o.zone}</span></div>
            <div class="oc-items">
                ${o.items.map(i=>`<div class="oc-item-row"><span>${i.name}</span><span class="qty">x${i.qty}</span></div>`).join('')}
            </div>
            <div class="oc-bottom">
                <div class="oc-price">${fmt(o.total)}</div>
                <div class="oc-actions">
                    <button class="btn-ghost" onclick="viewReceipt('${o.id}')">RECEIPT</button>
                    ${draggable ? '<span class="drag-hint">&middot; DRAG</span>' : ''}
                </div>
            </div>
            ${truck ? `<div class="oc-assigned-tag">&#8594; assigned to ${truck.name}</div>` : ''}
        </div>`;
    }).join('');

    // attach drag events
    list.querySelectorAll('.order-card[draggable="true"]').forEach(card=>{
        card.addEventListener('dragstart', e=>{
            dragOrderId = card.dataset.order;
            card.classList.add('dragging');
        });
        card.addEventListener('dragend', ()=> card.classList.remove('dragging'));
    });
}

function renderTrucks(){
    const grid = document.getElementById('truckGrid');
    grid.innerHTML = trucks.map(truck=>{
        const assignedOrders = orders.filter(o=>o.truck===truck.id && o.status!=='delivered' && o.status!=='returned');
        const cargo = truckCargo(truck);
        const segCount = 10;
        const filledSegs = Math.round((cargo/truck.capacity)*segCount);

        let statusBadgeClass = truck.status==='loading' ? 'pending' : truck.status==='transit' ? 'transit' : truck.status==='idle' ? '' : 'delivered';
        let statusLabel = truck.status.toUpperCase();

        let ordersHtml = assignedOrders.length ? assignedOrders.map(o=>`
            <div class="tc-order" data-order="${o.id}">
                <div class="tc-order-top">
                    <span class="tc-order-id">${o.id}</span>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <span class="badge zone-${o.zone.toLowerCase()}">${o.zone}</span>
                        <button class="icon-btn" onclick="viewReceipt('${o.id}')" title="View">${svg.eye}</button>
                        ${truck.status==='loading' ? `<button class="icon-btn danger" onclick="unassign('${o.id}')" title="Remove">${svg.x}</button>` : ''}
                    </div>
                </div>
                <div class="tc-order-name">${o.customer}</div>
                <div class="tc-order-items">
                    ${o.items.map(i=>`<div><span>${i.name}</span><span>x${i.qty}</span></div>`).join('')}
                    <div style="color:var(--text-faint);margin-top:2px;">${o.items.length} item${o.items.length>1?'s':''}</div>
                </div>
            </div>`).join('') : `<div class="dropzone-empty">${truck.status==='idle' ? 'NO CARGO ASSIGNED' : 'DRAG A RECEIPT HERE'}</div>`;

        let actionsHtml = '';
        if(truck.status==='loading'){
            actionsHtml = `
                <div class="tc-actions">
                    <button class="btn btn-dispatch" onclick="dispatchTruck('${truck.id}')" ${assignedOrders.length===0?'disabled style="opacity:.4;cursor:not-allowed;"':''}>${svg.arrow} DISPATCH</button>
                    <button class="btn btn-cancel" onclick="clearTruck('${truck.id}')" title="Clear all">${svg.x}</button>
                </div>`;
        } else if(truck.status==='transit'){
            actionsHtml = `
                <div class="tc-actions">
                    <button class="btn btn-delivered" onclick="markDelivered('${truck.id}')">${svg.check} MARK DELIVERED</button>
                    <button class="btn btn-return" onclick="markReturned('${truck.id}')" title="No one claimed it">${svg.back}</button>
                </div>
                <div class="tc-departed">DEPARTED: ${truck.departed}</div>`;
        }

        return `
        <div class="truck-card" data-truck="${truck.id}">
            <div class="tc-top">
                <div>
                    <div class="tc-name">${svg.truck} ${truck.name} ${statusBadgeClass ? `<span class="badge ${statusBadgeClass}">${statusLabel}</span>` : `<span class="badge" style="color:var(--text-dim)">${statusLabel}</span>`}</div>
                    <div class="tc-driver">${truck.driver} &middot; ${truck.plate}</div>
                </div>
            </div>
            <div>
                <div class="cargo-label"><span>CARGO</span><b>${cargo}/${truck.capacity}</b></div>
                <div class="cargo-bar">
                    ${Array.from({length:segCount}).map((_,i)=>`<div class="cargo-seg ${i<filledSegs?'filled':''}"></div>`).join('')}
                </div>
            </div>
            <div class="tc-orders">${ordersHtml}</div>
            ${actionsHtml}
        </div>`;
    }).join('');

    // dropzones = truck cards themselves (except transit/delivered trucks accept no new drops)
    grid.querySelectorAll('.truck-card').forEach(card=>{
        const truckId = card.dataset.truck;
        const truck = trucks.find(t=>t.id===truckId);
        if(truck.status !== 'loading' && truck.status !== 'idle') return;

        card.addEventListener('dragover', e=>{
            e.preventDefault();
            card.classList.add('drop-active');
        });
        card.addEventListener('dragleave', ()=> card.classList.remove('drop-active'));
        card.addEventListener('drop', e=>{
            e.preventDefault();
            card.classList.remove('drop-active');
            if(dragOrderId) assignOrder(dragOrderId, truckId);
            dragOrderId = null;
        });
    });
}

/* ---------------- ACTIONS ---------------- */
function assignOrder(orderId, truckId){
    const order = orders.find(o=>o.id===orderId);
    const truck = trucks.find(t=>t.id===truckId);
    if(!order || !truck) return;

    const currentCargo = truckCargo(truck);
    if(currentCargo + cargoOf(order) > truck.capacity){
        alert(`${truck.name} doesn't have enough capacity left for this order.`);
        return;
    }

    order.truck = truckId;
    order.status = 'assigned';
    if(truck.status === 'idle') truck.status = 'loading';
    render();
}

function unassign(orderId){
    const order = orders.find(o=>o.id===orderId);
    if(!order) return;
    order.truck = null;
    order.status = 'pending';
    // if truck now has zero cargo, revert to idle
    trucks.forEach(t=>{
        if(t.status==='loading' && truckCargo(t)===0) t.status = 'idle';
    });
    render();
}

function clearTruck(truckId){
    orders.filter(o=>o.truck===truckId).forEach(o=>{ o.truck=null; o.status='pending'; });
    const truck = trucks.find(t=>t.id===truckId);
    truck.status = 'idle';
    render();
}

function dispatchTruck(truckId){
    const truck = trucks.find(t=>t.id===truckId);
    const assigned = orders.filter(o=>o.truck===truckId);
    if(!assigned.length) return;
    truck.status = 'transit';
    truck.departed = new Date().toLocaleString('en-US', { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' });
    assigned.forEach(o=> o.status = 'transit');
    render();
}

function markDelivered(truckId){
    const truck = trucks.find(t=>t.id===truckId);
    orders.filter(o=>o.truck===truckId).forEach(o=>{ o.status='delivered'; });
    truck.status = 'delivered';
    render();
    setTimeout(()=>{ resetTruck(truckId); }, 1600);
}

function markReturned(truckId){
    const truck = trucks.find(t=>t.id===truckId);
    orders.filter(o=>o.truck===truckId).forEach(o=>{ o.status='returned'; });
    truck.status = 'delivered'; // back at base, unclaimed load returned
    render();
    setTimeout(()=>{ resetTruck(truckId); }, 1600);
}

function resetTruck(truckId){
    const truck = trucks.find(t=>t.id===truckId);
    // detach delivered/returned orders from truck view, free the truck up
    orders.forEach(o=>{ if(o.truck===truckId) o.truck = null; });
    truck.status = 'idle';
    truck.departed = null;
    render();
}

function viewReceipt(orderId){
    const o = orders.find(x=>x.id===orderId);
    if(!o) return;
    const lines = o.items.map(i=>`  ${i.name} x${i.qty}`).join('\n');
    alert(`RECEIPT ${o.id}\n${o.customer}\n${o.address}\n\n${lines}\n\nTOTAL: ${fmt(o.total)}`);
}

/* ---------------- TABS ---------------- */
document.getElementById('tabs').addEventListener('click', e=>{
    const tab = e.target.closest('.tab');
    if(!tab) return;
    activeTab = tab.dataset.tab;
    document.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
    tab.classList.add('active');
    renderOrderList();
});

render();
</script>
</body>
</html>
