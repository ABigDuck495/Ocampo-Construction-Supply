/* ============================================================
   DELIVERY OPS - DATA + LOGIC
   ============================================================ */

/* ---------------- DATA ---------------- */
let orders = [
    { id:'RC-041823', customer:'Juan dela Cruz', contact:'0917 234 5671', address:'123 Magsaysay St, Brgy. San Jose', notes:'', orderType:'Delivery',
      items:[{name:'Titanium Hammer',qty:2},{name:'Hex Bolt M8 x50',qty:5}], total:85.58, payment:'COD', paymentStatus:'Unpaid', status:'transit', truck:'T1' },
    { id:'RC-041891', customer:'Maria Santos', contact:'0918 345 6782', address:'45 Rizal Ave, Brgy. Poblacion', notes:'Leave with the guard if no one answers.', orderType:'Delivery',
      items:[{name:'PVC Pipe 3/4"',qty:5},{name:'Circuit Breaker 20A',qty:2}], total:52.08, payment:'GCash', paymentStatus:'Paid', status:'pending', truck:null },
    { id:'RC-041956', customer:'Pedro Reyes', contact:'0919 456 7893', address:'789 Luna St, Brgy. Sta. Cruz', notes:'', orderType:'Delivery',
      items:[{name:'Cordless Drill 18V',qty:1},{name:'Safety Gloves L',qty:2}], total:64.30, payment:'Card', paymentStatus:'Paid', status:'transit', truck:'T2' },
    { id:'RC-042004', customer:'Ana Lopez', contact:'0920 567 8904', address:'12 Bonifacio St, Brgy. San Jose', notes:'', orderType:'Delivery',
      items:[{name:'Paint Roller Set',qty:3},{name:'Latex Paint 1L',qty:4}], total:47.15, payment:'Bank Transfer', paymentStatus:'Paid', status:'pending', truck:null },
    { id:'RC-042017', customer:'Carlo Dizon', contact:'0921 678 9015', address:'Pickup at store', notes:'Will pick up after 5pm.', orderType:'Pickup',
      items:[{name:'Steel Nails 1kg',qty:6}], total:18.90, payment:'COD', paymentStatus:'Unpaid', status:'pending', truck:null },
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
    phone:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>',
    note:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 13h6M9 17h6"/></svg>',
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

function badgeForPayment(payment){
    if(!payment) return '';
    const cls = payment.toLowerCase().replace(/\s+/g, '-');
    return `<span class="badge payment-${cls}">${payment}</span>`;
}

function badgeForPaymentStatus(paymentStatus){
    if(!paymentStatus) return '';
    const cls = paymentStatus.toLowerCase();
    return `<span class="badge status-${cls}">${paymentStatus}</span>`;
}

function badgeForOrderType(orderType){
    if(orderType !== 'Pickup') return '';
    return `<span class="badge pickup">PICKUP</span>`;
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
        const isPickup = o.orderType === 'Pickup';
        const draggable = (o.status==='pending' || o.status==='assigned') && !isPickup;
        return `
        <div class="order-card ${o.status==='assigned'?'is-assigned':''}" data-order="${o.id}" ${draggable?'draggable="true"':''}>
            <div class="oc-top">
                <div class="oc-id"><span class="grip">${draggable?'::':''}</span>${o.id}</div>
                <div class="oc-badges-top">${badgeForOrderType(o.orderType)}${badgeFor(o.status)}</div>
            </div>
            <div class="oc-name">${o.customer}</div>
            <div class="oc-contact">${svg.phone}${o.contact || '—'}</div>
            <div class="oc-addr">${svg.pin}${o.address}</div>
            ${o.notes ? `<div class="oc-notes">${svg.note}${o.notes}</div>` : ''}
            <div class="oc-items">
                ${o.items.map(i=>`<div class="oc-item-row"><span>${i.name}</span><span class="qty">x${i.qty}</span></div>`).join('')}
            </div>
            <div class="oc-bottom">
                <div class="oc-price">${fmt(o.total)}</div>
                <div class="oc-badges">${badgeForPayment(o.payment)}${badgeForPaymentStatus(o.paymentStatus)}</div>
            </div>
            <div class="oc-actions">
                <button class="btn-ghost" onclick="viewReceipt('${o.id}')">RECEIPT</button>
                ${draggable ? '<span class="drag-hint">&middot; DRAG</span>' : ''}
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
                        ${badgeForPayment(o.payment)}
                        ${badgeForPaymentStatus(o.paymentStatus)}
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
    const notesLine = o.notes ? `\nNotes: ${o.notes}` : '';
    const addressLine = o.orderType === 'Pickup' ? '' : `\n${o.address}`;
    alert(`RECEIPT ${o.id}\n${o.customer}\nContact: ${o.contact || 'N/A'}\nType: ${o.orderType || 'Delivery'}${addressLine}${notesLine}\nPayment: ${o.payment || 'N/A'} (${o.paymentStatus || 'N/A'})\n\n${lines}\n\nTOTAL: ${fmt(o.total)}`);
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

/* ---------------- EXPOSE TO GLOBAL SCOPE ----------------
   Required because Vite loads this file as an ES module.
   Module-scoped functions are NOT automatically attached to
   `window`, but the inline onclick="..." attributes in the
   HTML strings above need them there to be callable.
*/
window.viewReceipt = viewReceipt;
window.unassign = unassign;
window.clearTruck = clearTruck;
window.dispatchTruck = dispatchTruck;
window.markDelivered = markDelivered;
window.markReturned = markReturned;
window.assignOrder = assignOrder;
