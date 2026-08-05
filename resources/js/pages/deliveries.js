/* ============================================================
   DELIVERY OPS - DATA + LOGIC (wired to backend)
   ============================================================ */

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

/* ---------------- DATA (from DispatchController@index) ----------------
   window.DISPATCH_DATA.orders  -> flat OrderItem[] still awaiting dispatch
   window.DISPATCH_DATA.trucks  -> Truck[] with .dispatches (active ones with .orderItem.order, .drivers)
   ------------------------------------------------------------------- */
const rawOrderItems = (window.DISPATCH_DATA && window.DISPATCH_DATA.orders) || [];
const rawTrucks = (window.DISPATCH_DATA && window.DISPATCH_DATA.trucks) || [];

function groupOrderItems(items) {
    const map = {};
    items.forEach(oi => {
        const oid = oi.OrderID;
        if (!map[oid]) {
            const order = oi.order || {};
            // OrderType does not exist – default to 'Delivery'
            const isPickup = false; // or derive from some flag if you add it later
            map[oid] = {
                id: 'ORD-' + oid,
                orderId: oid,
                customer: order.CustomerName || 'Unknown',
                contact: order.ContactNumber || '',
                address: order.Address || (isPickup ? 'Pickup at store' : ''),
                notes: order.Notes || '',
                orderType: isPickup ? 'Pickup' : 'Delivery',
                paymentStatus: order.PaymentStatus || '',
                total: 0,
                items: [],
                orderItemIds: [],
                status: 'pending',
                truck: null,
            };
        }
        const product = oi.product || {};
        map[oid].items.push({
            name: product.Product_Name || 'Item',
            qty: oi.Quantity,
            orderItemId: oi.OrderItemID,
        });
        map[oid].orderItemIds.push(oi.OrderItemID);
    });
    return Object.values(map);
}

function mapTrucks(list) {
    return list.map(t => {
        const activeDispatches = (t.dispatches || []).filter(d => d.Status === 'On Route');
        const mainDriver = activeDispatches[0]?.drivers?.find(d => d.pivot?.Role === 'Main');

        let status = 'idle';
        if (t.Status === 'On Route') status = 'transit';
        else if (t.Status === 'Maintenance') status = 'maintenance';

        return {
            id: t.TruckID,
            name: t.TruckName,
            driver: mainDriver?.Name || '—',
            plate: t.PlateNumber,           // ← fixed field name
            capacity: Number(t.Capacity) || 0,
            status: status,
            departed: activeDispatches[0]?.DispatchDate || null,
            activeDispatchIds: activeDispatches.map(d => d.DispatchID),
        };
    });
}

let orders = groupOrderItems(rawOrderItems);
let trucks = mapTrucks(rawTrucks);

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

function fmt(n){ return '$' + Number(n || 0).toFixed(2); }
function cargoOf(order){ return order.items.reduce((s,i)=>s + (parseFloat(i.qty) || 0), 0); }
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

    document.getElementById('statPending').textContent = pending;
    document.getElementById('statTransit').textContent = transit;
    document.getElementById('statDone').textContent = delivered;

    const headerSub = document.getElementById('headerSub');
    if (headerSub) {
        const customText = headerSub.dataset.defaultText?.trim();
        headerSub.textContent = customText || `${orders.length} orders · ${trucks.length} trucks`;
    }

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
    return `<span class="badge ${status}">${map[status] || status}</span>`;
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
        const filledSegs = truck.capacity ? Math.round((cargo/truck.capacity)*segCount) : 0;

        let statusBadgeClass = truck.status==='loading' ? 'pending' : truck.status==='transit' ? 'transit' : truck.status==='idle' ? '' : 'delivered';
        let statusLabel = truck.status.toUpperCase();

        const ordersHtml = assignedOrders.map(o => `
            <div class="tc-order-row">
                <span>${o.id} &middot; ${o.customer}</span>
                ${truck.status==='loading' ? `<button class="tc-unassign" onclick="unassign('${o.id}')" title="Unassign">${svg.x}</button>` : ''}
            </div>`).join('');

        let actionsHtml = '';
        if(truck.status==='loading'){
            actionsHtml = `
                <div class="tc-actions">
                    <button class="btn btn-dispatch" onclick="dispatchTruck('${truck.id}')">${svg.arrow} DISPATCH</button>
                    <button class="btn-ghost" onclick="clearTruck('${truck.id}')">CLEAR</button>
                </div>`;
        } else if(truck.status==='transit'){
            actionsHtml = `
                <div class="tc-actions">
                    <button class="btn btn-delivered" onclick="markDelivered('${truck.id}')">${svg.check} MARK DELIVERED</button>
                    <button class="btn btn-return" onclick="markReturned('${truck.id}')" title="No one claimed it">${svg.back}</button>
                </div>
                <div class="tc-departed">DEPARTED: ${truck.departed || ''}</div>`;
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

    grid.querySelectorAll('.truck-card').forEach(card=>{
        const truckId = card.dataset.truck;
        const truck = trucks.find(t=>String(t.id)===String(truckId));
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

// Purely client-side staging - no Dispatch record exists yet until dispatchTruck() runs.
function assignOrder(orderId, truckId){
    const order = orders.find(o=>o.id===orderId);
    const truck = trucks.find(t=>String(t.id)===String(truckId));
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
    trucks.forEach(t=>{
        if(t.status==='loading' && truckCargo(t)===0) t.status = 'idle';
    });
    render();
}

function clearTruck(truckId){
    orders.filter(o=>String(o.truck)===String(truckId)).forEach(o=>{ o.truck=null; o.status='pending'; });
    const truck = trucks.find(t=>String(t.id)===String(truckId));
    truck.status = 'idle';
    render();
}

// Picks one available driver as "Main" for this dispatch, since the UI has no driver picker.
async function pickDriverForDispatch(){
    try {
        const res = await fetch('/drivers/available');
        if(!res.ok) return null;
        const drivers = await res.json();
        return drivers[0]?.DriverID || null;
    } catch (err) {
        console.error('Could not fetch available drivers:', err);
        return null;
    }
}

// Real dispatch - creates one Dispatch record per OrderItem assigned to this truck.
async function dispatchTruck(truckId){
    const truck = trucks.find(t=>String(t.id)===String(truckId));
    const assigned = orders.filter(o=>String(o.truck)===String(truckId));
    if(!assigned.length) return;

    const driverId = await pickDriverForDispatch();
    if(!driverId){
        alert('No available drivers to assign to this dispatch.');
        return;
    }

    const dispatchDate = new Date().toISOString().split('T')[0];

    try {
        for (const order of assigned) {
            for (const item of order.items) {
                const res = await fetch('/dispatches', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        OrderItemID: item.orderItemId,
                        TruckID: truckId,
                        QuantityDispatched: parseFloat(item.qty) || 1,
                        DispatchDate: dispatchDate,
                        drivers: [{ DriverID: driverId, Role: 'Main' }],
                    }),
                });

                if(!res.ok){
                    const err = await res.json().catch(() => ({}));
                    throw new Error(err.message || `Failed to dispatch item for order ${order.id}`);
                }
            }
        }

        // Reload to get fresh truth from the server (real Dispatch/Truck statuses).
        window.location.reload();
    } catch (err) {
        console.error('Dispatch failed:', err);
        alert(err.message || 'Something went wrong while dispatching.');
    }
}

// Confirms delivery for every active Dispatch currently on this truck.
async function markDelivered(truckId){
    const truck = trucks.find(t=>String(t.id)===String(truckId));
    if(!truck || !truck.activeDispatchIds.length) return;

    try {
        for (const dispatchId of truck.activeDispatchIds) {
            const res = await fetch(`/dispatches/${dispatchId}/deliveries`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    QuantityDelivered: 0, // backend uses the dispatched quantity; adjust here if partials matter
                    Status: 'Delivered',
                }),
            });
            if(!res.ok){
                const err = await res.json().catch(() => ({}));
                throw new Error(err.message || 'Failed to confirm delivery.');
            }
        }
        window.location.reload();
    } catch (err) {
        console.error('Mark delivered failed:', err);
        alert(err.message || 'Something went wrong confirming delivery.');
    }
}

// Confirms a failed/returned delivery for every active Dispatch on this truck.
async function markReturned(truckId){
    const truck = trucks.find(t=>String(t.id)===String(truckId));
    if(!truck || !truck.activeDispatchIds.length) return;

    try {
        for (const dispatchId of truck.activeDispatchIds) {
            const res = await fetch(`/dispatches/${dispatchId}/deliveries`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    QuantityDelivered: 0,
                    Status: 'Returned',
                }),
            });
            if(!res.ok){
                const err = await res.json().catch(() => ({}));
                throw new Error(err.message || 'Failed to mark as returned.');
            }
        }
        window.location.reload();
    } catch (err) {
        console.error('Mark returned failed:', err);
        alert(err.message || 'Something went wrong marking the return.');
    }
}

function viewReceipt(orderId){
    const o = orders.find(x=>x.id===orderId);
    if(!o) return;
    const lines = o.items.map(i=>`  ${i.name} x${i.qty}`).join('\n');
    const notesLine = o.notes ? `\nNotes: ${o.notes}` : '';
    const addressLine = o.orderType === 'Pickup' ? '' : `\n${o.address}`;
    alert(`RECEIPT ${o.id}\n${o.customer}\nContact: ${o.contact || 'N/A'}\nType: ${o.orderType || 'Delivery'}${addressLine}${notesLine}\nPayment status: ${o.paymentStatus || 'N/A'}\n\n${lines}`);
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

/* ---------------- EXPOSE TO GLOBAL SCOPE ---------------- */
window.viewReceipt = viewReceipt;
window.unassign = unassign;
window.clearTruck = clearTruck;
window.dispatchTruck = dispatchTruck;
window.markDelivered = markDelivered;
window.markReturned = markReturned;
window.assignOrder = assignOrder;