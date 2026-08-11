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
                isSplitFrom: null, // set on a "sent" card carved off a pasabay order via applyPasabaySplit()
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
        const boardmate = activeDispatches[0]?.drivers?.find(d => d.pivot?.Role === 'Assistant');

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
let pasabaySplitCounter = 1; // used to build unique ids for "sent" cards carved off a pasabay order

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
            ${o.isSplitFrom ? `<div class="oc-pasabay-tag">PASABAY &middot; part of ${o.isSplitFrom}</div>` : ''}
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
                    <div class="tc-driver">${truck.driver}${truck.boardmate ? ' + ' + truck.boardmate : ''} &middot; ${truck.plate}</div>
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
            if(dragOrderId) openAssignModal(dragOrderId, truckId);
            dragOrderId = null;
        });
    });
}

/* ---------------- MODAL STYLES (shared by the assign + driver-picker modals) ---------------- */

let modalStylesInjected = false;
function injectAssignModalStyles(){
    if (modalStylesInjected) return;
    modalStylesInjected = true;
    const style = document.createElement('style');
    style.textContent = `
        .doa-modal-overlay{position:fixed;inset:0;background:rgba(20,15,10,.65);display:flex;align-items:center;justify-content:center;z-index:1000;font-family:'JetBrains Mono',monospace;padding:20px;}
        .doa-modal-box{background:#fffaf0;color:#171310;border:3px solid #171310;border-radius:10px;box-shadow:6px 6px 0 #171310;padding:22px 24px;width:380px;max-width:100%;max-height:85vh;overflow-y:auto;}
        .doa-modal-head{display:flex;justify-content:space-between;align-items:center;font-weight:800;letter-spacing:.5px;font-size:14px;color:#171310;margin-bottom:4px;}
        .doa-modal-x{background:#171310;color:#fffaf0;border:none;border-radius:6px;cursor:pointer;width:26px;height:26px;display:flex;align-items:center;justify-content:center;transition:background .15s;flex-shrink:0;}
        .doa-modal-x:hover{background:#e0592a;}
        .doa-modal-x svg{width:14px;height:14px;}
        .doa-modal-sub{font-size:11.5px;color:#6b6258;line-height:1.5;margin:8px 0 16px;}
        .doa-item-list{display:flex;flex-direction:column;gap:6px;margin-bottom:14px;border-top:2px solid #171310;border-bottom:2px solid #171310;padding:10px 0;}
        .doa-item-row{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:12.5px;padding:4px 2px;}
        .doa-item-name{flex:1;font-weight:600;color:#171310;}
        .doa-item-avail{font-size:10.5px;color:#93897c;white-space:nowrap;}
        .doa-qty-input{width:56px;background:#fff;color:#171310;border:2px solid #171310;border-radius:5px;padding:5px 6px;font-family:inherit;font-weight:700;text-align:center;}
        .doa-qty-input:focus{outline:none;border-color:#e0592a;}
        .doa-modal-note{font-size:11px;font-weight:600;color:#6b6258;margin-bottom:16px;background:#f2ece0;border:1px solid #ddd3c2;border-radius:6px;padding:6px 10px;}
        .doa-modal-note.over{color:#b3261e;background:#fbe2df;border-color:#f0aca5;}
        .doa-field-label{display:block;font-size:10.5px;color:#171310;font-weight:800;margin:12px 0 5px;letter-spacing:.6px;}
        .doa-optional{opacity:.55;font-weight:500;text-transform:none;letter-spacing:0;}
        .doa-select{width:100%;background:#fff;color:#171310;border:2px solid #171310;border-radius:6px;padding:7px 9px;font-family:inherit;font-weight:600;margin-bottom:4px;}
        .doa-select:focus{outline:none;border-color:#e0592a;}
        .doa-modal-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:18px;}
        .doa-receipt-box{background:#fffaf0;color:#171310;width:320px;max-width:100%;max-height:85vh;overflow-y:auto;padding:20px 22px 22px;position:relative;border:3px solid #171310;border-radius:10px;box-shadow:6px 6px 0 #171310;}
        .doa-receipt-header{text-align:center;margin:6px 0 4px;}
        .doa-receipt-store{font-weight:800;font-size:12.5px;letter-spacing:.5px;line-height:1.4;}
        .doa-receipt-sub{font-size:10px;letter-spacing:1.5px;color:#93897c;margin-top:5px;}
        .doa-receipt-divider{border-top:2px dashed #ddd3c2;margin:14px 0;}
        .doa-receipt-meta{font-size:11.5px;line-height:1.8;}
        .doa-receipt-meta b{font-weight:800;letter-spacing:.3px;}
        .doa-receipt-item{display:flex;align-items:baseline;gap:6px;font-size:12.5px;padding:3px 0;}
        .doa-receipt-item-name{white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:62%;}
        .doa-receipt-leader{flex:1;border-bottom:1px dotted #b7ac9c;margin-bottom:3px;}
        .doa-receipt-item-qty{font-weight:700;white-space:nowrap;}
        .doa-receipt-total{display:flex;justify-content:space-between;font-size:13px;font-weight:800;letter-spacing:.3px;}
        .doa-receipt-footer{text-align:center;font-size:10px;letter-spacing:2px;color:#93897c;margin-top:16px;}
        .doa-receipt-close{width:100%;margin-top:16px;}
    `;
    document.head.appendChild(style);
}

/* ---------------- PASABAY: partial-item assignment ---------------- */

// Opens a per-item quantity picker before an order actually lands on a truck.
// Whatever isn't sent now stays on the original card as still-pending stock,
// ready to go out on the next delivery run.
function openAssignModal(orderId, truckId){
    const order = orders.find(o=>o.id===orderId);
    const truck = trucks.find(t=>String(t.id)===String(truckId));
    if(!order || !truck) return;

    const remainingCapacity = truck.capacity - truckCargo(truck);

    const rows = order.items.map((item, idx) => `
        <div class="doa-item-row">
            <span class="doa-item-name">${item.name}</span>
            <span class="doa-item-avail">of ${item.qty}</span>
            <input type="number" class="doa-qty-input" data-idx="${idx}" min="0" max="${item.qty}" value="${item.qty}" step="1">
        </div>
    `).join('');

    const overlay = document.createElement('div');
    overlay.className = 'doa-modal-overlay';
    overlay.innerHTML = `
        <div class="doa-modal-box">
            <div class="doa-modal-head">
                <div>SEND WITH ${truck.name.toUpperCase()}</div>
                <button class="doa-modal-x" id="doaCancel">${svg.x}</button>
            </div>
            <div class="doa-modal-sub">Pick how many of each item to send now (pasabay). Anything left over stays pending for the next delivery run.</div>
            <div class="doa-item-list">${rows}</div>
            <div class="doa-modal-note" id="doaCapNote"></div>
            <div class="doa-modal-actions">
                <button class="btn-ghost" id="doaCancelBtn">CANCEL</button>
                <button class="btn btn-dispatch" id="doaConfirmBtn">CONFIRM</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    injectAssignModalStyles();

    function currentCargo(){
        return Array.from(overlay.querySelectorAll('.doa-qty-input')).reduce((s, inp) => s + (parseFloat(inp.value) || 0), 0);
    }
    function updateCapNote(){
        const cargo = currentCargo();
        const note = overlay.querySelector('#doaCapNote');
        note.textContent = `${cargo}/${remainingCapacity} of remaining truck capacity`;
        note.classList.toggle('over', cargo > remainingCapacity);
    }
    updateCapNote();
    overlay.querySelectorAll('.doa-qty-input').forEach(inp => inp.addEventListener('input', updateCapNote));

    function close(){ overlay.remove(); }
    overlay.querySelector('#doaCancel').addEventListener('click', close);
    overlay.querySelector('#doaCancelBtn').addEventListener('click', close);
    overlay.querySelector('#doaConfirmBtn').addEventListener('click', () => {
        const chosen = Array.from(overlay.querySelectorAll('.doa-qty-input')).map(inp => ({
            idx: Number(inp.dataset.idx),
            qty: Math.max(0, Math.min(parseFloat(inp.value) || 0, order.items[Number(inp.dataset.idx)].qty)),
        }));
        const cargo = chosen.reduce((s, c) => s + c.qty, 0);
        if(cargo <= 0){ alert('Pick at least one item to send.'); return; }
        if(cargo > remainingCapacity){ alert(`${truck.name} doesn't have enough capacity left for this selection.`); return; }
        close();
        applyPasabaySplit(order, truck, chosen);
    });
}

// Splits an order's items between "sent now" and "kept for next time" based on
// the quantities chosen in openAssignModal(), per the pasabay concept.
function applyPasabaySplit(order, truck, chosen){
    const sentItems = [];
    const keptItems = [];

    order.items.forEach((item, idx) => {
        const picked = chosen.find(c => c.idx === idx)?.qty || 0;
        if(picked > 0) sentItems.push({ ...item, qty: picked });

        const leftover = item.qty - picked;
        if(leftover > 0) keptItems.push({ ...item, qty: leftover });
    });

    const fullySent = keptItems.length === 0;

    if(fullySent){
        // Nothing held back - behaves like a normal, non-pasabay assignment.
        order.items = sentItems;
        order.truck = truck.id;
        order.status = 'assigned';
    } else {
        // Carve off a new card for the sent portion; the original card stays
        // pending with only the leftover quantities (pasabay for next time).
        const sentOrder = {
            ...order,
            id: order.id + '-P' + (pasabaySplitCounter++),
            items: sentItems,
            orderItemIds: sentItems.map(i => i.orderItemId),
            truck: truck.id,
            status: 'assigned',
            isSplitFrom: order.id,
        };
        order.items = keptItems;
        order.orderItemIds = keptItems.map(i => i.orderItemId);
        order.truck = null;
        order.status = 'pending';
        orders.push(sentOrder);
    }

    if(truck.status === 'idle') truck.status = 'loading';
    render();
}

/* ---------------- ACTIONS ---------------- */

// Rolls an assigned card's items back into pending. If it was a pasabay "sent"
// card, its items are merged back into the original leftover card instead of
// leaving a stray duplicate on the board.
function mergeItemsBackToPending(order){
    const parentId = order.isSplitFrom || order.id;
    const parent = orders.find(o => o.id === parentId && o !== order);

    if(parent){
        order.items.forEach(item => {
            const existing = parent.items.find(i => i.orderItemId === item.orderItemId);
            if(existing) existing.qty += item.qty;
            else parent.items.push({ ...item });
        });
        parent.orderItemIds = parent.items.map(i => i.orderItemId);
        orders.splice(orders.indexOf(order), 1);
    } else {
        order.truck = null;
        order.status = 'pending';
    }
}

function unassign(orderId){
    const order = orders.find(o=>o.id===orderId);
    if(!order) return;
    mergeItemsBackToPending(order);
    trucks.forEach(t=>{
        if(t.status==='loading' && truckCargo(t)===0) t.status = 'idle';
    });
    render();
}

function clearTruck(truckId){
    orders.filter(o=>String(o.truck)===String(truckId)).slice().forEach(o => mergeItemsBackToPending(o));
    const truck = trucks.find(t=>String(t.id)===String(truckId));
    truck.status = 'idle';
    render();
}

// Drivers have no "role" column - Main vs Assistant is a property of the
// dispatch assignment itself, not the person - so both the driver and
// boardmate pickers draw from the same available-drivers list.
async function fetchAvailableDrivers(){
    try {
        const res = await fetch('/drivers/available');
        if(!res.ok) return [];
        return await res.json();
    } catch (err) {
        console.error('Could not fetch available drivers:', err);
        return [];
    }
}

// Lets the dispatcher pick a driver (required) and a boardmate (optional)
// before the truck leaves. Resolves { driverId, boardmateId } or null if cancelled.
function openDriverPickerModal(){
    return new Promise(async (resolve) => {
        const drivers = await fetchAvailableDrivers();

        if(!drivers.length){
            alert('No available drivers to assign to this dispatch.');
            resolve(null);
            return;
        }

        const optionsFor = (excludeId) => drivers
            .filter(d => String(d.DriverID) !== String(excludeId))
            .map(d => `<option value="${d.DriverID}">${d.Name}</option>`)
            .join('');

        const overlay = document.createElement('div');
        overlay.className = 'doa-modal-overlay';
        overlay.innerHTML = `
            <div class="doa-modal-box">
                <div class="doa-modal-head">
                    <div>ASSIGN DRIVER</div>
                    <button class="doa-modal-x" id="dpCancel">${svg.x}</button>
                </div>
                <label class="doa-field-label">DRIVER</label>
                <select class="doa-select" id="dpDriver">${optionsFor(null)}</select>
                <label class="doa-field-label">BOARDMATE <span class="doa-optional">(optional)</span></label>
                <select class="doa-select" id="dpBoardmate">
                    <option value="">— none —</option>
                    ${optionsFor(drivers[0]?.DriverID)}
                </select>
                <div class="doa-modal-actions">
                    <button class="btn-ghost" id="dpCancelBtn">CANCEL</button>
                    <button class="btn btn-dispatch" id="dpConfirmBtn">CONFIRM &amp; DISPATCH</button>
                </div>
            </div>`;
        document.body.appendChild(overlay);
        injectAssignModalStyles();

        const driverSelect = overlay.querySelector('#dpDriver');
        const boardmateSelect = overlay.querySelector('#dpBoardmate');

        // Keep the boardmate list from ever including whoever is picked as the main driver.
        function refreshBoardmateOptions(){
            const chosenBoardmate = boardmateSelect.value;
            boardmateSelect.innerHTML = `<option value="">— none —</option>${optionsFor(driverSelect.value)}`;
            if(chosenBoardmate && chosenBoardmate !== driverSelect.value) boardmateSelect.value = chosenBoardmate;
        }
        refreshBoardmateOptions();
        driverSelect.addEventListener('change', refreshBoardmateOptions);

        function close(result){ overlay.remove(); resolve(result); }
        overlay.querySelector('#dpCancel').addEventListener('click', () => close(null));
        overlay.querySelector('#dpCancelBtn').addEventListener('click', () => close(null));
        overlay.querySelector('#dpConfirmBtn').addEventListener('click', () => {
            const driverId = driverSelect.value;
            const boardmateId = boardmateSelect.value;
            close({ driverId, boardmateId: boardmateId || null });
        });
    });
}

// Real dispatch - creates one Dispatch record per OrderItem assigned to this truck,
// after the dispatcher confirms a driver (and optional boardmate) in the picker.
async function dispatchTruck(truckId){
    const truck = trucks.find(t=>String(t.id)===String(truckId));
    const assigned = orders.filter(o=>String(o.truck)===String(truckId));
    if(!assigned.length) return;

    const picked = await openDriverPickerModal();
    if(!picked) return;

    const dispatchDrivers = [{ DriverID: picked.driverId, Role: 'Main' }];
    if(picked.boardmateId) dispatchDrivers.push({ DriverID: picked.boardmateId, Role: 'Assistant' });

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
                        drivers: dispatchDrivers,
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

    const addressLine = o.orderType === 'Pickup' ? 'Pickup at store' : (o.address || '');

    const itemsHtml = o.items.map(i => `
        <div class="doa-receipt-item">
            <span class="doa-receipt-item-name">${i.name}</span>
            <span class="doa-receipt-leader"></span>
            <span class="doa-receipt-item-qty">x${i.qty}</span>
        </div>
    `).join('');

    const overlay = document.createElement('div');
    overlay.className = 'doa-modal-overlay';
    overlay.innerHTML = `
        <div class="doa-receipt-box">
            <button class="doa-modal-x" id="rcptClose" style="position:absolute;top:14px;right:14px;">${svg.x}</button>
            <div class="doa-receipt-header">
                <div class="doa-receipt-store">OCAMPO CONSTRUCTION &amp; HARDWARE SUPPLIES</div>
                <div class="doa-receipt-sub">DELIVERY RECEIPT &middot; ${o.id}</div>
            </div>
            <div class="doa-receipt-divider"></div>
            <div class="doa-receipt-meta">
                <div><b>CUSTOMER</b> &middot; ${o.customer}</div>
                <div><b>CONTACT</b> &middot; ${o.contact || 'N/A'}</div>
                <div><b>TYPE</b> &middot; ${o.orderType || 'Delivery'}</div>
                ${addressLine ? `<div><b>ADDRESS</b> &middot; ${addressLine}</div>` : ''}
                ${o.notes ? `<div><b>NOTES</b> &middot; ${o.notes}</div>` : ''}
            </div>
            <div class="doa-receipt-divider"></div>
            <div class="doa-receipt-items">${itemsHtml}</div>
            <div class="doa-receipt-divider"></div>
            <div class="doa-receipt-total"><span>PAYMENT STATUS</span><span>${o.paymentStatus || 'N/A'}</span></div>
            <div class="doa-receipt-footer">THANK YOU FOR YOUR BUSINESS</div>
            <button class="btn btn-dispatch doa-receipt-close" id="rcptCloseBtn">CLOSE</button>
        </div>`;
    document.body.appendChild(overlay);
    injectAssignModalStyles();

    function close(){ overlay.remove(); }
    overlay.querySelector('#rcptClose').addEventListener('click', close);
    overlay.querySelector('#rcptCloseBtn').addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if(e.target === overlay) close(); });
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