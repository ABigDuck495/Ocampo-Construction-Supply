/* ============================================================
   DELIVERY OPS - DATA + LOGIC (wired to backend)
   ============================================================ */

import { printReceipt } from './printReceipt.js';
import '../pt210-printer.js';

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

/* ---------------- DATA (from DispatchController@index) ----------------
   window.DISPATCH_DATA.orders  -> flat OrderItem[] still awaiting dispatch
   window.DISPATCH_DATA.trucks  -> Truck[] with .dispatches (Pending + On Route + past ones, with .orderItem.order/.product, .drivers)
   ------------------------------------------------------------------- */
const rawOrderItems = (window.DISPATCH_DATA && window.DISPATCH_DATA.orders) || [];
const rawTrucks = (window.DISPATCH_DATA && window.DISPATCH_DATA.trucks) || [];
const SYS = (window.SYSTEM_SETTINGS) ? window.SYSTEM_SETTINGS : {};
const TRUCK_CAPACITY_TRACKING = SYS.enable_truck_capacity_tracking === 'true';

function groupOrderItems(items) {
    const map = {};
    items.forEach(oi => {
        const oid = oi.OrderID;
        const order = oi.order || {};
        const transaction = order.transactions || {};
        const customer = (order.CustomerName || 'Unknown').trim();
        const groupKey = `${oid}_${customer.toLowerCase()}`;
        if (!map[groupKey]) {
            const isPickup = false;
            map[groupKey] = {
                id: 'ORD-' + oid,
                orderId: oid,
                customer,
                contact: order.ContactNumber || '',
                address: order.Address || (isPickup ? 'Pickup at store' : ''),
                notes: order.Notes || '',
                orderType: isPickup ? 'Pickup' : 'Delivery',
                paymentStatus: order.PaymentStatus || '',
                payment: transaction.PaymentMethod || '',
                total: Number(transaction.Amount) || 0,
                items: [],
                orderItemIds: [],
                status: 'pending',
                truck: null,
                isSplitFrom: null,
            };
        }
        const product = oi.product || {};
        map[groupKey].items.push({
            name: product.Product_Name || 'Item',
            qty: oi.Quantity,
            orderItemId: oi.OrderItemID,
        });
        map[groupKey].orderItemIds.push(oi.OrderItemID);
    });
    return Object.values(map);
}

/* ---------------- TRUCK MAPPING ----------------
   Truck.Status (backend) drives everything:
     Idle      -> no active work
     Loading   -> dispatch(es) created, awaiting driver acceptance on their device
     On Route  -> driver accepted, out for delivery
     Maintenance -> out of service
   activeDispatches holds every Pending/On Route dispatch on this truck,
   sourced straight from the backend (not the local pre-dispatch `orders`
   pool), since once a dispatch exists, its OrderItem drops out of the
   unassigned-items query.

   allDispatches holds EVERY dispatch (any status) for this truck, used to
   power the global Dispatch Log panel (deliveries + items tabs).
   ------------------------------------------------------------------- */
function mapTrucks(list) {
    return list.map(t => {
        const relevantDispatches = (t.dispatches || []).filter(d => d.Status === 'On Route' || d.Status === 'Pending');
        const onRouteDispatches = relevantDispatches.filter(d => d.Status === 'On Route');
        const referenceDispatch = onRouteDispatches[0] || relevantDispatches[0];
        const mainDriver = referenceDispatch?.drivers?.find(d => d.pivot?.Role === 'Driver');
        const boardmate = referenceDispatch?.drivers?.find(d => d.pivot?.Role === 'Helper');

        let status = 'idle';
        if (onRouteDispatches.length > 0) status = 'transit';
        else if (relevantDispatches.some(d => d.Status === 'Pending')) status = 'loading';
        else if (t.Status === 'Loading') status = 'loading';
        else if (t.Status === 'Maintenance') status = 'maintenance';

        const activeDispatches = relevantDispatches.map(d => {
            const orderItem = d.orderItem || {};
            const order = orderItem.order || {};
            return {
                dispatchId: d.DispatchID,
                status: d.Status, // 'Pending' | 'On Route'
                orderLabel: 'ORD-' + (orderItem.OrderID ?? order.OrderID ?? '—'),
                customer: order.CustomerName || 'Unknown',
                address: order.Address || '',
                driver: d.drivers?.find(driver => driver.pivot?.Role === 'Driver')?.Name || '—',
                itemName: orderItem.product?.Product_Name || 'Item',
                qty: Number(d.QuantityDispatched) || 0,
                acceptedAt: d.AcceptedAt || null,
            };
        });

        // Full dispatch history (any status) - powers the Dispatch Log panel
        const allDispatches = (t.dispatches || []).map(d => {
            const orderItem = d.orderItem || {};
            const order = orderItem.order || {};
            const mainD = d.drivers?.find(dr => dr.pivot?.Role === 'Driver');
            const helperD = d.drivers?.find(dr => dr.pivot?.Role === 'Helper');
            return {
                dispatchId: d.DispatchID,
                status: d.Status,
                truckName: t.TruckName,
                orderLabel: 'ORD-' + (orderItem.OrderID ?? order.OrderID ?? '—'),
                customer: order.CustomerName || 'Unknown',
                driver: mainD?.Name || '—',
                helper: helperD?.Name || null,
                itemName: orderItem.product?.Product_Name || 'Item',
                qty: Number(d.QuantityDispatched) || 0,
                timestamp: d.AcceptedAt || d.DispatchDate || d.created_at || null,
            };
        });

        return {
            id: t.TruckID,
            name: t.TruckName,
            driver: mainDriver?.Name || '—',
            boardmate: boardmate?.Name || null,
            plate: t.PlateNumber,
            capacity: Number(t.Capacity) || 0,
            status: status,
            departed: onRouteDispatches[0]?.AcceptedAt || null,
            activeDispatchIds: onRouteDispatches.map(d => d.DispatchID),
            activeDispatches: activeDispatches,
            allDispatches: allDispatches,
        };
    });
}

let orders = groupOrderItems(rawOrderItems);
let trucks = mapTrucks(rawTrucks);

let activeTab = 'all';
let dragOrderId = null;
let pasabaySplitCounter = 1;
const pendingDispatchDriversByTruck = new Map();

/* ---------------- DISPATCH LOG STATE ---------------- */
let dispatchLogEntries = [];
let activeLogTab = 'deliveries';

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
    clock:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>',
    log:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h16M4 18h10"/></svg>',
};

function fmt(n){ return '₱' + Number(n || 0).toFixed(2); }
function fmtDateTime(iso){
    if(!iso) return '—';
    const d = new Date(iso);
    if (isNaN(d.getTime())) return iso;
    return d.toLocaleString('en-US', { month:'short', day:'numeric', hour:'numeric', minute:'2-digit' });
}
function cargoOf(order){ return order.items.reduce((s,i)=>s + (parseFloat(i.qty) || 0), 0); }
function truckCargo(truck){
    return orders.filter(o=>o.truck===truck.id).reduce((s,o)=>s+cargoOf(o),0);
}
function truckActiveCargo(truck){
    return (truck.activeDispatches || []).reduce((s,d)=>s + (parseFloat(d.qty) || 0), 0);
}

function render(){
    renderStats();
    renderOrderList();
    renderTrucks();
    dispatchLogEntries = buildDispatchLog();
    renderDispatchLog();
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
function statusBadgeForDispatch(status){
    const map = { Pending:'pending', 'On Route':'transit', Delivered:'delivered', Failed:'returned', Cancelled:'returned' };
    const cls = map[status] || '';
    return `<span class="badge ${cls}">${(status || '').toUpperCase()}</span>`;
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
                ${o.items.map(i=>`<div class="oc-item-row"><span class="qty">${i.qty}×</span><span>${i.name}</span></div>`).join('')}
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
        const localAssigned = orders.filter(o=>o.truck===truck.id && o.status!=='delivered' && o.status!=='returned');
        const backendActive = truck.activeDispatches || [];
        const isBuilding = truck.status === 'loading' && backendActive.length === 0 && localAssigned.length > 0;
        const isAwaitingAcceptance = truck.status === 'loading' && backendActive.some(d => d.status === 'Pending');
        const isOnRoute = truck.status === 'transit';

        const cargo = isBuilding ? truckCargo(truck) : truckActiveCargo(truck);
        const segCount = 10;
        const filledSegs = truck.capacity ? Math.round((cargo/truck.capacity)*segCount) : 0;

        let statusBadgeClass = truck.status==='loading' ? 'pending' : truck.status==='transit' ? 'transit' : truck.status==='idle' ? '' : 'delivered';
        let statusLabel = isAwaitingAcceptance ? 'AWAITING DRIVER' : truck.status.toUpperCase();

        let ordersHtml = '';
        let actionsHtml = '';

        if (isBuilding) {
            // Pre-dispatch: items staged locally, nothing sent to the backend yet.
            ordersHtml = localAssigned.map(o => `
                <div class="tc-order-row">
                    <span>${o.id} &middot; ${o.customer}</span>
                    <button class="tc-unassign" onclick="unassign('${o.id}')" title="Unassign">${svg.x}</button>
                </div>`).join('');

            actionsHtml = `
                <div class="tc-actions">
                    <button class="btn btn-dispatch" onclick="dispatchTruck('${truck.id}')">${svg.arrow} DISPATCH</button>
                    <button class="btn-ghost" onclick="clearTruck('${truck.id}')">CLEAR</button>
                </div>`;
        } else if (isAwaitingAcceptance) {
            const groups = groupDispatchesByOrder(backendActive);
            ordersHtml = groups.map(g => `
                <div class="tc-order-group">
                    <div class="tc-order-group-head">${g.orderLabel} &middot; ${g.customer}</div>
                    ${g.items.map(d => `
                        <div class="tc-order-item-line" style="justify-content:space-between;">
                            <span><span class="qty">${d.qty}&times;</span> ${d.itemName}</span>
                            <button class="tc-unassign" onclick="cancelPendingDispatch(${d.dispatchId})" title="Cancel">${svg.x}</button>
                        </div>`).join('')}
                </div>
            `).join('');

            actionsHtml = `
                <div class="tc-awaiting-note">${svg.clock} Waiting for driver to accept on their device&hellip;</div>
                <div class="tc-actions">
                    <button class="btn-ghost" onclick="viewDispatchLog('${truck.id}')">${svg.log} LOG</button>
                </div>`;
        } else if (isOnRoute) {
            const groups = groupDispatchesByOrder(backendActive);
            ordersHtml = groups.map(g => `
                <div class="tc-order-group">
                    <div class="tc-order-group-head">${g.orderLabel} &middot; ${g.customer}</div>
                    ${g.items.map(d => `
                        <div class="tc-order-item-line"><span class="qty">${d.qty}&times;</span><span>${d.itemName}</span></div>
                    `).join('')}
                    <div class="tc-order-addr">${svg.pin}${g.address || '—'}</div>
                </div>
            `).join('');

            actionsHtml = `
                <div class="tc-actions">
                    <button class="btn btn-delivered" onclick="openDeliveryConfirmModal('${truck.id}')">${svg.check} SUCCESSFUL</button>
                    <button class="btn btn-return" onclick="markReturned('${truck.id}')" title="Dispatch failed">${svg.back} FAILED</button>
                </div> 
                <div class="tc-departed">${svg.clock} ACCEPTED: ${fmtDateTime(truck.departed)}</div>`;
        }

        const isLocked = isOnRoute;

        return `
        <div class="truck-card ${isLocked ? 'is-full' : ''}" data-truck="${truck.id}">
            <div class="tc-top">
                <div>
                    <div class="tc-name">${svg.truck} ${truck.name} ${statusBadgeClass ? `<span class="badge ${statusBadgeClass}">${statusLabel}</span>` : `<span class="badge" style="color:var(--text-dim)">${statusLabel}</span>`}</div>
                    <div class="tc-driver">${truck.driver}${truck.boardmate ? ' + ' + truck.boardmate : ''} &middot; ${truck.plate}</div>
                </div>
                <button class="tc-details-btn" type="button" onclick="showTruckDetails('${truck.id}')">${svg.eye} DETAILS</button>
            </div>
            <div>
                <div class="cargo-label"><span>CARGO</span><b>${cargo}/${truck.capacity}</b></div>
                <div class="cargo-bar">
                    ${Array.from({length:segCount}).map((_,i)=>`<div class="cargo-seg ${i<filledSegs?'filled':''}"></div>`).join('')}
                </div>
            </div>
            <div class="tc-orders">${ordersHtml || '<div class="dropzone-empty">No active items</div>'}</div>
            ${actionsHtml}
        </div>`;
    }).join('');

    grid.querySelectorAll('.truck-card').forEach(card=>{
        const truckId = card.dataset.truck;
        const truck = trucks.find(t=>String(t.id)===String(truckId));
        if(truck.status !== 'loading' && truck.status !== 'idle') return;
        if((truck.activeDispatches || []).length > 0) return; // already dispatched, not a drop target

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

/* ---------------- DISPATCH LOG (right column) ---------------- */

function buildDispatchLog(){
    const entries = [];
    trucks.forEach(t => entries.push(...(t.allDispatches || [])));
    entries.sort((a, b) => new Date(b.timestamp || 0) - new Date(a.timestamp || 0));
    return entries;
}

function groupDispatchesByOrder(dispatches) {
    const map = new Map();
    dispatches.forEach(d => {
        const key = `${d.orderLabel}_${d.customer}`;
        if (!map.has(key)) {
            map.set(key, {
                orderLabel: d.orderLabel,
                customer: d.customer,
                address: d.address || '',
                items: [],
            });
        }
        map.get(key).items.push(d);
    });
    return Array.from(map.values());
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, character => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    })[character]);
}

function showTruckDetails(truckId) {
    const truck = trucks.find(item => String(item.id) === String(truckId));
    if (!truck) return;

    const groupsByOrder = new Map();
    (truck.activeDispatches || []).forEach(dispatch => {
        const key = `${dispatch.orderLabel}_${dispatch.customer}`;
        if (!groupsByOrder.has(key)) {
            groupsByOrder.set(key, {
                orderLabel: dispatch.orderLabel,
                customer: dispatch.customer,
                address: dispatch.address,
                drivers: [],
                statuses: [],
                items: [],
            });
        }
        const group = groupsByOrder.get(key);
        group.items.push(dispatch);
        if (dispatch.driver && dispatch.driver !== '—' && !group.drivers.includes(dispatch.driver)) group.drivers.push(dispatch.driver);
        if (dispatch.status && !group.statuses.includes(dispatch.status)) group.statuses.push(dispatch.status);
    });

    const stagedOrders = orders.filter(order => String(order.truck) === String(truck.id) && !['delivered', 'returned'].includes(order.status));
    stagedOrders.forEach(order => groupsByOrder.set(`staged_${order.id}`, {
        orderLabel: order.id,
        customer: order.customer,
        address: order.address,
        drivers: [],
        statuses: ['Not dispatched'],
        items: order.items.map(item => ({ itemName: item.name, qty: item.qty })),
    }));

    const truckStatus = {
        idle: 'Idle',
        loading: 'Loading',
        transit: 'On Route',
        maintenance: 'Maintenance',
        delivered: 'Delivered',
    }[truck.status] || truck.status;
    const groups = Array.from(groupsByOrder.values());
    const groupsHtml = groups.length ? groups.map(group => `
        <section class="doa-delivery-group">
            <div class="doa-delivery-order">${escapeHtml(group.orderLabel)}</div>
            <div class="doa-delivery-fields">
                <div><b>CUSTOMER</b><span>${escapeHtml(group.customer || 'Unknown')}</span></div>
                <div><b>ADDRESS</b><span>${escapeHtml(group.address || '—')}</span></div>
                <div><b>DRIVER</b><span>${escapeHtml(group.drivers.join(', ') || truck.driver || '—')}</span></div>
                <div><b>TRUCK</b><span>${escapeHtml(truck.name || '—')}</span></div>
                <div><b>ORDER CODE</b><span>${escapeHtml(group.orderLabel)}</span></div>
                <div><b>STATUS</b><span>${escapeHtml(group.statuses.join(', ') || truckStatus)}</span></div>
            </div>
            <div class="doa-delivery-items">
                ${group.items.map(item => `<div><span>${escapeHtml(item.itemName || 'Item')}</span><b>${escapeHtml(item.qty)}×</b></div>`).join('')}
            </div>
        </section>
    `).join('') : '<div class="doa-modal-sub">No active delivery orders assigned to this truck.</div>';

    const overlay = document.createElement('div');
    overlay.className = 'doa-modal-overlay';
    overlay.innerHTML = `
        <div class="doa-modal-box doa-delivery-modal" role="dialog" aria-modal="true" aria-labelledby="truckDetailsTitle">
            <div class="doa-modal-head">
                <div id="truckDetailsTitle">DELIVERY DETAILS &middot; ${escapeHtml(truck.name || 'TRUCK')}</div>
                <button class="doa-modal-x" type="button" aria-label="Close details">${svg.x}</button>
            </div>
            <div class="doa-delivery-truck-status">TRUCK STATUS <b>${escapeHtml(truckStatus)}</b></div>
            <div class="doa-delivery-groups">${groupsHtml}</div>
        </div>`;
    document.body.appendChild(overlay);
    injectAssignModalStyles();

    const close = () => overlay.remove();
    overlay.querySelector('.doa-modal-x').addEventListener('click', close);
    overlay.addEventListener('click', event => { if (event.target === overlay) close(); });
    overlay.addEventListener('keydown', event => { if (event.key === 'Escape') close(); });
    overlay.querySelector('.doa-modal-x').focus();
}

function renderDispatchLog(){
    const list = document.getElementById('logList');
    if(!list) return;

    if(!dispatchLogEntries.length){
        list.innerHTML = `<div class="empty-state">NO DISPATCH ACTIVITY YET</div>`;
        return;
    }

    if(activeLogTab === 'deliveries'){
        list.innerHTML = dispatchLogEntries.map(e => `
            <div class="log-row">
                <div class="log-row-top">
                    <span class="log-order">${e.orderLabel} &middot; ${e.customer}</span>
                    ${statusBadgeForDispatch(e.status)}
                </div>
                <div class="log-row-sub">${svg.truck} ${e.truckName} &middot; ${e.driver}${e.helper ? ' + ' + e.helper : ''}</div>
                <div class="log-row-time">${svg.clock} ${fmtDateTime(e.timestamp)}</div>
            </div>`).join('');
    } else {
        list.innerHTML = dispatchLogEntries.map(e => `
            <div class="log-row">
                <div class="log-row-top">
                    <span class="log-order">${e.itemName}</span>
                    <span class="log-qty">x${e.qty}</span>
                </div>
                <div class="log-row-sub">${e.orderLabel} &middot; ${e.customer} &middot; ${e.truckName}</div>
                <div class="log-row-time">${svg.clock} ${fmtDateTime(e.timestamp)}</div>
            </div>`).join('');
    }
}

/* ---------------- MODAL STYLES (shared by all modals) ---------------- */

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
        .doa-textarea{width:100%;background:#fff;color:#171310;border:2px solid #171310;border-radius:6px;padding:8px 9px;font-family:inherit;font-weight:500;resize:vertical;min-height:60px;}
        .doa-textarea:focus{outline:none;border-color:#e0592a;}
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
        .doa-receipt-actions{display:flex;gap:8px;margin-top:16px;}
        .doa-receipt-actions .doa-receipt-close{margin-top:0;}
        .doa-log-row{display:flex;gap:10px;padding:8px 0;border-bottom:1px solid #ece4d4;font-size:11.5px;}
        .doa-log-row:last-child{border-bottom:none;}
        .doa-log-action{font-weight:800;min-width:110px;}
        .doa-log-time{color:#93897c;font-size:10px;white-space:nowrap;}
        .doa-log-notes{color:#6b6258;flex:1;}
        .doa-log-group-title{font-weight:800;font-size:11px;letter-spacing:.4px;margin:14px 0 4px;color:#e0592a;}
        .doa-log-group-title:first-child{margin-top:0;}
        .doa-delivery-modal{width:min(640px,100%);}
        .doa-delivery-truck-status{display:flex;justify-content:space-between;gap:12px;margin:12px 0;padding:9px 10px;background:#f2ece0;border:1px solid #ddd3c2;font-size:10px;font-weight:800;}
        .doa-delivery-truck-status b{color:#e0592a;}
        .doa-delivery-groups{display:grid;gap:10px;}
        .doa-delivery-group{border:1px solid #ddd3c2;padding:12px;}
        .doa-delivery-order{font-size:12px;font-weight:800;margin-bottom:10px;color:#e0592a;}
        .doa-delivery-fields{display:grid;grid-template-columns:1fr 1fr;gap:8px 14px;}
        .doa-delivery-fields div{display:grid;gap:3px;min-width:0;}
        .doa-delivery-fields b{font-size:9px;letter-spacing:.04em;color:#93897c;}
        .doa-delivery-fields span{font-size:11px;line-height:1.45;overflow-wrap:anywhere;}
        .doa-delivery-items{display:grid;gap:5px;margin-top:12px;padding-top:9px;border-top:1px dashed #ddd3c2;}
        .doa-delivery-items div{display:flex;justify-content:space-between;gap:10px;font-size:11px;}
        .doa-delivery-items b{white-space:nowrap;}
        @media(max-width:560px){.doa-delivery-fields{grid-template-columns:1fr;}}
    `;
    document.head.appendChild(style);
}

/* ---------------- PASABAY: partial-item assignment (pre-dispatch) ---------------- */

async function openAssignModal(orderId, truckId){
    const order = orders.find(o=>o.id===orderId);
    const truck = trucks.find(t=>String(t.id)===String(truckId));
    if(!order || !truck) return;

    const remainingCapacity = TRUCK_CAPACITY_TRACKING ? truck.capacity - truckCargo(truck) : Number.POSITIVE_INFINITY;
    const truckKey = String(truckId);
    const hasWaitingOrder = orders.some(item => String(item.truck) === truckKey && item.status === 'assigned');
    const savedSelection = pendingDispatchDriversByTruck.get(truckKey);
    const reuseDriverSelection = hasWaitingOrder && Boolean(savedSelection?.driverId);
    const drivers = reuseDriverSelection ? [] : await fetchAvailableDrivers();

    const rows = order.items.map((item, idx) => `
        <div class="doa-item-row">
            <span class="doa-item-name">${item.name}</span>
            <span class="doa-item-avail">of ${item.qty}</span>
            <input type="number" class="doa-qty-input" data-idx="${idx}" min="0" max="${item.qty}" value="${item.qty}" step="1">
        </div>
    `).join('');

    const mainDriverSelect = (selectedId = '') => `
        <option value="">Select driver</option>
        ${drivers.map(d => `<option value="${d.DriverID}" ${String(d.DriverID) === String(selectedId) ? 'selected' : ''}>${d.Name}</option>`).join('')}
    `;

    const buildHelperOptions = (selectedMainId = '', selectedHelperId = '') => {
        const options = drivers
            .filter(d => String(d.DriverID) !== String(selectedMainId))
            .map(d => `<option value="${d.DriverID}" ${String(d.DriverID) === String(selectedHelperId) ? 'selected' : ''}>${d.Name}</option>`)
            .join('');

        return `<option value="">— none —</option>${options}`;
    };

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
            ${reuseDriverSelection ? '' : `
                <label class="doa-field-label">MAIN DRIVER</label>
                <select class="doa-select" id="doaMainDriver" ${drivers.length ? '' : 'disabled'}>
                    ${mainDriverSelect()}
                </select>

                <label class="doa-field-label">HELPER <span class="doa-optional">(optional)</span></label>
                <select class="doa-select" id="doaHelperDriver">
                    ${buildHelperOptions()}
                </select>
            `}

            <div class="doa-modal-actions">
                <button class="btn-ghost" id="doaCancelBtn">CANCEL</button>
                <button class="btn btn-dispatch" id="doaConfirmBtn">CONFIRM</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    injectAssignModalStyles();

    const mainDriverSelectEl = overlay.querySelector('#doaMainDriver');
    const helperDriverSelectEl = overlay.querySelector('#doaHelperDriver');

    mainDriverSelectEl?.addEventListener('change', () => {
        const selectedMainId = mainDriverSelectEl.value;
        const currentlySelectedHelper = helperDriverSelectEl.value;
        helperDriverSelectEl.innerHTML = buildHelperOptions(selectedMainId, currentlySelectedHelper === selectedMainId ? '' : currentlySelectedHelper);
    });

    function currentCargo(){
        return Array.from(overlay.querySelectorAll('.doa-qty-input')).reduce((s, inp) => s + (parseFloat(inp.value) || 0), 0);
    }
    function updateCapNote(){
        if (!TRUCK_CAPACITY_TRACKING) {
            overlay.querySelector('#doaCapNote').textContent = '';
            return;
        }
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
        const selection = reuseDriverSelection ? savedSelection : {
            driverId: overlay.querySelector('#doaMainDriver')?.value || '',
            boardmateId: overlay.querySelector('#doaHelperDriver')?.value || null,
        };

        if(cargo <= 0){ alert('Pick at least one item to send.'); return; }
        if (TRUCK_CAPACITY_TRACKING && cargo > remainingCapacity){ alert(`${truck.name} doesn't have enough capacity left for this selection.`); return; }
        if (!selection?.driverId) { alert('Please select a main driver before dispatching.'); return; }

        pendingDispatchDriversByTruck.set(truckKey, selection);
        close();
        applyPasabaySplit(order, truck, chosen);
    });
}

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
        order.items = sentItems;
        order.truck = truck.id;
        order.status = 'assigned';
    } else {
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
        if(t.status==='loading' && truckCargo(t)===0 && (t.activeDispatches||[]).length===0) t.status = 'idle';
    });
    render();
}

function clearTruck(truckId){
    orders.filter(o=>String(o.truck)===String(truckId)).slice().forEach(o => mergeItemsBackToPending(o));
    const truck = trucks.find(t=>String(t.id)===String(truckId));
    truck.status = 'idle';
    render();
}

/**
 * Cancels a dispatch that's still awaiting the driver's acceptance
 * (backend status 'Pending'). Releases the item back to the unassigned pool.
 */
async function cancelPendingDispatch(dispatchId){
    if(!confirm('Cancel this dispatch? The item will return to the pending pool.')) return;
    try {
        const res = await fetch(`/dispatches/${dispatchId}/cancel`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
        });
        if(!res.ok){
            const err = await res.json().catch(() => ({}));
            throw new Error(err.message || 'Failed to cancel dispatch.');
        }
        window.location.reload();
    } catch (err) {
        console.error('Cancel dispatch failed:', err);
        alert(err.message || 'Something went wrong cancelling the dispatch.');
    }
}

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

/**
 * Creates the dispatch(es) for a truck's staged items. Backend creates them
 * as 'Pending' and moves the truck to 'Loading' — the driver still needs
 * to accept on their own device before it becomes 'On Route'.
 */
async function dispatchTruck(truckId, presetDrivers = null){
    const truck = trucks.find(t=>String(t.id)===String(truckId));
    const assigned = orders.filter(o=>String(o.truck)===String(truckId));
    if(!assigned.length) return;

    const selectedDrivers = presetDrivers || pendingDispatchDriversByTruck.get(String(truckId)) || await openDriverPickerModal();
    if(!selectedDrivers) return;

    const dispatchDrivers = [{ DriverID: selectedDrivers.driverId, Role: 'Driver' }];
    if(selectedDrivers.boardmateId) dispatchDrivers.push({ DriverID: selectedDrivers.boardmateId, Role: 'Helper' });

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

        window.location.reload();
    } catch (err) {
        console.error('Dispatch failed:', err);
        alert(err.message || 'Something went wrong while dispatching.');
    }
}

/**
 * Opens the delivery confirmation modal for a truck that's On Route,
 * letting the dispatcher set the actual delivered quantity per item.
 * Anything short of the dispatched quantity (pasabay) is automatically
 * returned to the pending pool by the backend.
 */
function openDeliveryConfirmModal(truckId){
    const truck = trucks.find(t=>String(t.id)===String(truckId));
    if(!truck) return;
    const items = (truck.activeDispatches || []).filter(d => d.status !== 'Delivered' && d.status !== 'Failed');
    if(!items.length){
        alert('This truck has no accepted deliveries yet. The driver must accept the dispatch first.');
        return;
    }

    const rows = items.map((d, idx) => `
        <div class="doa-item-row">
            <span class="doa-item-name">${d.orderLabel} &middot; ${d.itemName}</span>
            <span class="doa-item-avail">of ${d.qty}</span>
            <input type="number" class="doa-qty-input" data-dispatch-id="${d.dispatchId}" data-max="${d.qty}" min="0" max="${d.qty}" value="${d.qty}" step="1">
        </div>
    `).join('');

    const overlay = document.createElement('div');
    overlay.className = 'doa-modal-overlay';
    overlay.innerHTML = `
        <div class="doa-modal-box">
            <div class="doa-modal-head">
                <div>CONFIRM DELIVERY &middot; ${truck.name.toUpperCase()}</div>
                <button class="doa-modal-x" id="dcCancel">${svg.x}</button>
            </div>
            <div class="doa-modal-sub">Enter what was actually delivered for each item. Anything less than the full amount stays pending for the next run (pasabay).</div>
            <div class="doa-item-list">${rows}</div>
            <label class="doa-field-label">NOTES <span class="doa-optional">(optional)</span></label>
            <textarea class="doa-textarea" id="dcNotes" placeholder="e.g. customer not home for 2 bags of cement"></textarea>
            <div class="doa-modal-actions">
                <button class="btn-ghost" id="dcCancelBtn">CANCEL</button>
                <button class="btn btn-delivered" id="dcConfirmBtn">${svg.check} CONFIRM DELIVERY</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    injectAssignModalStyles();

    function close(){ overlay.remove(); }
    overlay.querySelector('#dcCancel').addEventListener('click', close);
    overlay.querySelector('#dcCancelBtn').addEventListener('click', close);
    overlay.querySelector('#dcConfirmBtn').addEventListener('click', async () => {
        const notes = overlay.querySelector('#dcNotes').value.trim();
        const entries = Array.from(overlay.querySelectorAll('.doa-qty-input')).map(inp => ({
            dispatchId: inp.dataset.dispatchId,
            qty: Math.max(0, Math.min(parseFloat(inp.value) || 0, Number(inp.dataset.max))),
        }));

        const confirmBtn = overlay.querySelector('#dcConfirmBtn');
        confirmBtn.disabled = true;
        confirmBtn.textContent = 'SAVING…';

        try {
            for (const entry of entries) {
                const res = await fetch(`/dispatches/${entry.dispatchId}/deliveries`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        QuantityDelivered: entry.qty,
                        Status: 'Delivered',
                        Notes: notes || null,
                    }),
                });
                if(!res.ok){
                    const err = await res.json().catch(() => ({}));
                    throw new Error(err.message || 'Failed to confirm delivery.');
                }
            }
            window.location.reload();
        } catch (err) {
            console.error('Confirm delivery failed:', err);
            alert(err.message || 'Something went wrong confirming delivery.');
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'CONFIRM DELIVERY';
        }
    });
}

/**
 * Marks every active dispatch on a truck as Failed (nothing delivered).
 * All items return to the pending pool for re-delivery.
 */
async function markReturned(truckId){
    const truck = trucks.find(t=>String(t.id)===String(truckId));
    if(!truck) return;
    if(!truck.activeDispatchIds.length){
        alert('This truck has no accepted deliveries to mark as failed.');
        return;
    }
    if(!confirm(`Mark ${truck.name}'s delivery as failed? All items will return to the pending pool.`)) return;

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
                    Status: 'Failed',
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

/**
 * Shows the full dispatch log (Dispatched -> Accepted -> Delivered/Failed
 * etc.) for every active dispatch on a truck.
 */
async function viewDispatchLog(truckId){
    const truck = trucks.find(t=>String(t.id)===String(truckId));
    if(!truck) return;
    const ids = (truck.activeDispatches || []).map(d => d.dispatchId);
    if(!ids.length) return;

    const overlay = document.createElement('div');
    overlay.className = 'doa-modal-overlay';
    overlay.innerHTML = `
        <div class="doa-modal-box">
            <div class="doa-modal-head">
                <div>DISPATCH LOG &middot; ${truck.name.toUpperCase()}</div>
                <button class="doa-modal-x" id="dlClose">${svg.x}</button>
            </div>
            <div id="dlBody" class="doa-modal-sub">Loading&hellip;</div>
        </div>`;
    document.body.appendChild(overlay);
    injectAssignModalStyles();
    overlay.querySelector('#dlClose').addEventListener('click', () => overlay.remove());

    try {
        const results = await Promise.all(ids.map(id =>
            fetch(`/dispatches/${id}`, { headers: { 'Accept': 'application/json' } }).then(r => r.json())
        ));

        const body = overlay.querySelector('#dlBody');
        body.innerHTML = results.map(d => {
            const label = `${d.orderItem?.product?.Product_Name || 'Item'} &middot; Dispatch #${d.DispatchID}`;
            const logs = (d.logs || []).map(l => `
                <div class="doa-log-row">
                    <span class="doa-log-action">${l.Action}</span>
                    <span class="doa-log-notes">${l.Notes || ''}</span>
                    <span class="doa-log-time">${fmtDateTime(l.LoggedAt)}</span>
                </div>
            `).join('') || '<div class="doa-log-row"><span class="doa-log-notes">No log entries yet.</span></div>';

            return `<div class="doa-log-group-title">${label}</div>${logs}`;
        }).join('');
    } catch (err) {
        console.error('Failed to load dispatch log:', err);
        overlay.querySelector('#dlBody').textContent = 'Could not load the dispatch log.';
    }
}

/* ----------------------------------------------------------
   Receipt printing
   ---------------------------------------------------------- */
function buildDeliveryPrintPayload(order){
    const addressLine = order.orderType === 'Pickup' ? 'Pickup at store' : (order.address || '');

    return {
        store_name: 'Ocampo Construction and Hardware Supplies',
        store_sub: 'Sual, Pangasinan',
        date: new Date().toLocaleString('en-US', { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' }),
        customer_name: order.customer,
        contact: order.contact,
        order_type: order.orderType || 'Delivery',
        address: addressLine || null,
        notes: order.notes || null,
        payment_status: order.paymentStatus || null,
        payment_method: order.payment || null,
        items: order.items.map(i => ({ name: i.name, qty: i.qty })),
        total: order.total,
        footer: 'Thank you for your business!',
    };
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
            <div class="doa-receipt-meta"><div><b>PAYMENT</b> &middot; ${o.payment || 'N/A'}</div></div>
            <div class="doa-receipt-total"><span>PAYMENT STATUS</span><span>${o.paymentStatus || 'N/A'}</span></div>
            <div class="doa-receipt-total"><span>TOTAL</span><span>${fmt(o.total)}</span></div>
            <div class="doa-receipt-footer">THANK YOU FOR YOUR BUSINESS</div>
            <div class="doa-receipt-actions">
                <button class="btn-ghost doa-receipt-close" id="rcptPrintBtn">PRINT</button>
                <button class="btn btn-dispatch doa-receipt-close" id="rcptCloseBtn">CLOSE</button>
            </div>
        </div>`;
    document.body.appendChild(overlay);
    injectAssignModalStyles();

    function close(){ overlay.remove(); }
    overlay.querySelector('#rcptClose').addEventListener('click', close);
    overlay.querySelector('#rcptCloseBtn').addEventListener('click', close);
    overlay.addEventListener('click', (e) => { if(e.target === overlay) close(); });

    const printBtn = overlay.querySelector('#rcptPrintBtn');
    printBtn.addEventListener('click', async () => {
        printBtn.disabled = true;
        const originalLabel = printBtn.textContent;
        printBtn.textContent = 'PRINTING…';
        try {
            const result = await printReceipt(buildDeliveryPrintPayload(o));
            if (result.status !== 'printed') {
                console.error('Delivery receipt print failed:', result.message);
                alert(result.message || 'Could not print the receipt.');
            }
        } catch (err) {
            console.error('Delivery receipt print error:', err);
            alert('Could not print the receipt.');
        } finally {
            printBtn.disabled = false;
            printBtn.textContent = originalLabel;
        }
    });
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

/* ---------------- DISPATCH LOG TABS ---------------- */
const logTabsEl = document.getElementById('logTabs');
if (logTabsEl) {
    logTabsEl.addEventListener('click', e=>{
        const tab = e.target.closest('.log-tab');
        if(!tab) return;
        activeLogTab = tab.dataset.logtab;
        document.querySelectorAll('.log-tab').forEach(t=>t.classList.remove('active'));
        tab.classList.add('active');
        renderDispatchLog();
    });
}

render();

/* ---------------- EXPOSE TO GLOBAL SCOPE ---------------- */
window.viewReceipt = viewReceipt;
window.unassign = unassign;
window.clearTruck = clearTruck;
window.dispatchTruck = dispatchTruck;
window.cancelPendingDispatch = cancelPendingDispatch;
window.openDeliveryConfirmModal = openDeliveryConfirmModal;
window.markReturned = markReturned;
window.viewDispatchLog = viewDispatchLog;
window.showTruckDetails = showTruckDetails;

// Works alongside the existing HTML5 drag-and-drop in deliveries.js.
// It does NOT handle drops – it only (1) marks the page as "dragging"
// so trucks light up as drop targets, and (2) auto-scrolls whichever
// panel the cursor is near the top/bottom edge of while dragging.

(function () {
    const EDGE = 60;       // px from a panel edge that triggers scrolling
    const MAX_SPEED = 18;  // px per dragover tick

    document.addEventListener('dragstart', (e) => {
        if (e.target.closest && e.target.closest('.order-card')) {
            document.body.classList.add('is-dragging');
        }
    });

    const end = () => document.body.classList.remove('is-dragging');
    document.addEventListener('dragend', end);
    document.addEventListener('drop', end);

    document.addEventListener('dragover', (e) => {
        if (!document.body.classList.contains('is-dragging')) return;

        const el = document.elementFromPoint(e.clientX, e.clientY);
        const panel = el && el.closest ? el.closest('.ws-scroll') : null;
        if (!panel) return;

        const r = panel.getBoundingClientRect();
        const fromTop = e.clientY - r.top;
        const fromBottom = r.bottom - e.clientY;

        if (fromTop < EDGE) {
            panel.scrollTop -= Math.ceil(MAX_SPEED * (1 - fromTop / EDGE));
        } else if (fromBottom < EDGE) {
            panel.scrollTop += Math.ceil(MAX_SPEED * (1 - fromBottom / EDGE));
        }
    });
})();