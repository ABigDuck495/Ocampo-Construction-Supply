/* ============================================================
   POS - DATA + LOGIC (wired to backend)
   ============================================================ */

import { printReceipt } from './printReceipt.js'
import { toast } from './toast.js'

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

/* ---------------- CATALOG (from PosController@index) ---------------- */
const rawProducts = (window.POS_DATA && window.POS_DATA.products) || [];

const products = rawProducts.map(p => ({
    id: p.ProductID,
    name: p.Product_Name,
    cat: p.Category || 'Hardware',
    price: Number(p.Price) || 0,
    stock: p.inventory ? Number(p.inventory.QuantityOnHand) : 0,
}));

const icons = {
    Tools:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
    Paint:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 11H7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h5"/><path d="M9 11V7a3 3 0 0 1 6 0v1"/><circle cx="18" cy="18" r="3"/></svg>',
    Hardware:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    Plumbing:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h6v6H4z"/><path d="M14 14h6v6h-6z"/><path d="M10 7h4v7h-4z"/></svg>',
    Electrical: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h7v8l10-12h-7z"/></svg>',
};
function iconFor(cat){ return icons[cat] || icons.Hardware; }

/* ---------------- STATE ---------------- */
let cart = [];
let activeCat = 'all';
let selectedPayment = null;
let orderType = 'Delivery';

function fmt(n){ return '$' + Number(n).toFixed(2); }

/* ---------------- RENDER: PRODUCTS ---------------- */
function renderProducts(){
    const grid = document.getElementById('productGrid');
    const list = activeCat === 'all' ? products : products.filter(p => p.cat === activeCat);

    grid.innerHTML = list.map(p => `
        <div class="product-card" data-id="${p.id}">
            <div class="product-icon">${iconFor(p.cat)}</div>
            <div class="product-name">${p.name}</div>
            <div class="product-cat">${p.cat}</div>
            <div class="product-price">${fmt(p.price)}</div>
        </div>`).join('');

    grid.querySelectorAll('.product-card').forEach(card => {
        card.addEventListener('click', () => addToCart(card.dataset.id));
    });
}

/* ---------------- CART LOGIC ---------------- */
function addToCart(productId){
    const product = products.find(p => String(p.id) === String(productId));
    if(!product) return;

    const existing = cart.find(c => String(c.id) === String(productId));
    const currentQty = existing ? existing.qty : 0;

    if(currentQty + 1 > product.stock){
        toast(`Not enough stock for ${product.name}. Available: ${product.stock}`, 'error');
        return;
    }

    if(existing){ existing.qty += 1; }
    else { cart.push({ id: product.id, name: product.name, price: product.price, qty: 1, stock: product.stock }); }
    renderCart();
}

function changeQty(productId, delta){
    const item = cart.find(c => String(c.id) === String(productId));
    if(!item) return;

    if(delta > 0 && item.qty + 1 > item.stock){
        toast(`Not enough stock for ${item.name}. Available: ${item.stock}`, 'error');
        return;
    }

    item.qty += delta;
    if(item.qty <= 0) cart = cart.filter(c => String(c.id) !== String(productId));
    renderCart();
}

function removeFromCart(productId){
    cart = cart.filter(c => String(c.id) !== String(productId));
    renderCart();
}

function cartTotal(){
    return cart.reduce((sum, i) => sum + i.price * i.qty, 0);
}
function cartItemCount(){
    return cart.reduce((sum, i) => sum + i.qty, 0);
}

function renderCart(){
    const wrap = document.getElementById('cartItems');
    const checkoutBtn = document.getElementById('checkoutBtn');

    if(!cart.length){
        wrap.innerHTML = `<div class="cart-empty">CART IS EMPTY</div>`;
    } else {
        wrap.innerHTML = cart.map(i => `
            <div class="cart-item" data-id="${i.id}">
                <div class="ci-top">
                    <div class="ci-name">${i.name}</div>
                    <button class="ci-remove" data-remove="${i.id}" title="Remove">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>
                <div class="ci-bottom">
                    <div class="ci-qty">
                        <button class="qty-btn" data-dec="${i.id}">-</button>
                        <span class="qty-val">${i.qty}</span>
                        <button class="qty-btn" data-inc="${i.id}">+</button>
                    </div>
                    <div class="ci-price">${fmt(i.price * i.qty)}</div>
                </div>
            </div>`).join('');
    }

    wrap.querySelectorAll('[data-inc]').forEach(b => b.addEventListener('click', () => changeQty(b.dataset.inc, 1)));
    wrap.querySelectorAll('[data-dec]').forEach(b => b.addEventListener('click', () => changeQty(b.dataset.dec, -1)));
    wrap.querySelectorAll('[data-remove]').forEach(b => b.addEventListener('click', () => removeFromCart(b.dataset.remove)));

    const total = cartTotal();
    document.getElementById('cartSubtotal').textContent = fmt(total);
    document.getElementById('cartTotal').textContent = fmt(total);
    document.getElementById('statCartItems').textContent = cartItemCount();
    document.getElementById('statCartTotal').textContent = fmt(total);
    checkoutBtn.disabled = cart.length === 0;
}

/* ---------------- CATEGORY TABS ---------------- */
document.getElementById('categoryTabs').addEventListener('click', e => {
    const tab = e.target.closest('.tab');
    if(!tab) return;
    activeCat = tab.dataset.cat;
    document.querySelectorAll('#categoryTabs .tab').forEach(t => t.classList.remove('active'));
    tab.classList.add('active');
    renderProducts();
});

/* ---------------- CLEAR CART ---------------- */
document.getElementById('clearCartBtn').addEventListener('click', () => {
    cart = [];
    renderCart();
});

/* ---------------- ORDER TYPE (DELIVERY / PICKUP) ---------------- */
document.getElementById('orderTypeToggle').addEventListener('click', e => {
    const opt = e.target.closest('.type-option');
    if(!opt) return;
    orderType = opt.dataset.type;
    document.querySelectorAll('#orderTypeToggle .type-option').forEach(o => o.classList.remove('selected'));
    opt.classList.add('selected');

    const isPickup = orderType === 'Pickup';
    document.getElementById('addressGroup').style.display = isPickup ? 'none' : '';
    document.getElementById('detailsTitle').textContent = isPickup ? 'PICKUP DETAILS' : 'DELIVERY DETAILS';
    document.getElementById('custAddress').placeholder = isPickup ? '' : 'Delivery address';
});

/* ---------------- PAYMENT METHOD ---------------- */
document.getElementById('paymentOptions').addEventListener('click', e => {
    const opt = e.target.closest('.payment-option');
    if(!opt) return;
    selectedPayment = opt.dataset.payment;
    document.querySelectorAll('#paymentOptions .payment-option').forEach(o => o.classList.remove('selected'));
    opt.classList.add('selected');
});

/* ---------------- CHECKOUT / RECEIPT ---------------- */
let pendingOrder = null;

document.getElementById('checkoutBtn').addEventListener('click', () => {
    const name = document.getElementById('custName').value.trim();
    const contact = document.getElementById('custContact').value.trim();
    const address = document.getElementById('custAddress').value.trim();
    const notes = document.getElementById('custNotes').value.trim();
    const paymentStatus = document.getElementById('custPaymentStatus').value;
    const isPickup = orderType === 'Pickup';

    if(!cart.length){ toast('Cart is empty.', 'error'); return; }
    if(!name || !contact || !paymentStatus){
        toast('Please fill in customer name, contact number, and payment status before checking out.', 'error');
        return;
    }
    if(!isPickup && !address){
        toast('Please fill in the delivery address before checking out.', 'error');
        return;
    }
    if(!selectedPayment){
        toast('Please select a payment method before checking out.', 'error');
        return;
    }

    pendingOrder = {
        customer: name,
        contact: contact,
        address: isPickup ? 'Pickup at store' : address,
        notes: notes,
        orderType: orderType,
        items: cart.map(i => ({ productId: i.id, name: i.name, qty: i.qty, price: i.price })),
        total: cartTotal(),
        payment: selectedPayment,
        paymentStatus: paymentStatus,
        date: formatReceiptDate(),
    };

    renderReceipt(pendingOrder);

    const isDelivery = orderType === 'Delivery';
    document.getElementById('confirmDeliveryBtn').style.display = isDelivery ? '' : 'none';
    document.getElementById('confirmPickupBtn').style.display = isDelivery ? 'none' : '';

    document.getElementById('receiptOverlay').classList.add('open');
});

function formatReceiptDate(){
    return new Date().toLocaleString('en-US', { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' });
}

function renderReceipt(order){
    const paper = document.getElementById('receiptPaper');
    const now = order.date || formatReceiptDate();

    paper.innerHTML = `
        <h2>Ocampo Construction and Hardware Supplies</h2>
        <div class="r-sub">Sual, Pangasinan</div>
        <div class="r-meta">Date: ${now}</div>
        <div class="r-meta">Customer: ${order.customer}</div>
        <div class="r-meta">Contact: ${order.contact}</div>
        <div class="r-meta">Type: ${order.orderType}</div>
        ${order.orderType === 'Delivery' ? `<div class="r-meta">Address: ${order.address}</div>` : ''}
        ${order.notes ? `<div class="r-meta">Notes: ${order.notes}</div>` : ''}
        <div class="r-meta">Payment: ${order.payment} (${order.paymentStatus})</div>
        <div class="r-line"></div>
        ${order.items.map(i => `<div class="r-row"><span>${i.name} x${i.qty}</span></div>`).join('')}
        <div class="r-line"></div>
        <div class="r-row total"><span>TOTAL</span><span>${fmt(order.total)}</span></div>
        <div class="r-line"></div>
        <div class="r-sub" style="text-align:center;margin-top:10px;">Thank you for your purchase!</div>
    `;
}

document.getElementById('receiptBackBtn').addEventListener('click', () => {
    document.getElementById('receiptOverlay').classList.remove('open');
    pendingOrder = null;
});

/* ----------------------------------------------------------
   Build the payload shape printReceipt() sends to the printer.
   ---------------------------------------------------------- */
function buildPrintPayload(order){
    return {
        store_name: 'Ocampo Construction and Hardware Supplies',
        store_sub: 'Sual, Pangasinan',
        date: order.date || formatReceiptDate(),
        customer_name: order.customer,
        contact: order.contact,
        order_type: order.orderType,
        address: order.orderType === 'Delivery' ? order.address : null,
        notes: order.notes || null,
        payment_method: order.payment,
        payment_status: order.paymentStatus,
        items: order.items.map(i => ({ name: i.name, qty: i.qty, price: i.price })),
        total: order.total,
        footer: 'Thank you for your purchase!',
    };
}

/* ---------------- PRINT (thermal printer, w/ browser-print fallback) ---------------- */
document.getElementById('printBtn').addEventListener('click', async () => {
    if(!pendingOrder){
        window.print();
        return;
    }

    const btn = document.getElementById('printBtn');
    btn.disabled = true;
    try {
        const result = await printReceipt(buildPrintPayload(pendingOrder));
        if(result.status !== 'printed'){
            console.error('Thermal print failed:', result.message);
            toast((result.message || 'Thermal print failed.') + ' Falling back to browser print.', 'error');
            window.print();
        } else {
            toast('Receipt printed.', 'success');
        }
    } catch (err) {
        console.error('Thermal print error:', err);
        toast('Could not reach the printer. Falling back to browser print.', 'error');
        window.print();
    } finally {
        btn.disabled = false;
    }
});

/* ----------------------------------------------------------
   Shared sale-confirmation logic used by BOTH the Delivery and
   Pickup confirm buttons.
   ---------------------------------------------------------- */
async function submitSale(triggerBtn){
    if(!pendingOrder) return;

    triggerBtn.disabled = true;
    const wasDelivery = pendingOrder.orderType === 'Delivery';
    const printPayload = buildPrintPayload(pendingOrder);

    try {
        const res = await fetch('/transactions/pos-sale', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                items: pendingOrder.items.map(i => ({
                    ProductID: i.productId,
                    Quantity: String(i.qty),
                    UnitPrice: i.price,
                })),
                PaymentMethod: pendingOrder.payment,
                OrderType: pendingOrder.orderType,
                CustomerName: pendingOrder.customer,
                ContactNumber: pendingOrder.contact,
                Address: pendingOrder.address,
                Notes: pendingOrder.notes,
                PaymentStatus: pendingOrder.paymentStatus,
            }),
        });

        if(!res.ok){
            const err = await res.json().catch(() => ({}));
            toast(err.message || 'Something went wrong processing the sale.', 'error');
            triggerBtn.disabled = false;
            return;
        }

        try {
            const printResult = await printReceipt(printPayload);
            if(printResult.status !== 'printed'){
                console.error('Thermal print failed:', printResult.message);
                toast((printResult.message || 'Thermal print failed.') + ' You can reprint from the receipt if needed.', 'error');
            } else {
                toast('Sale saved and receipt printed.', 'success');
            }
        } catch (printErr) {
            console.error('Thermal print error:', printErr);
            toast('Sale saved, but could not reach the printer.', 'error');
        }

        // reset POS state
        cart = [];
        pendingOrder = null;
        selectedPayment = null;
        orderType = 'Delivery';
        document.getElementById('custName').value = '';
        document.getElementById('custContact').value = '';
        document.getElementById('custAddress').value = '';
        document.getElementById('custNotes').value = '';
        document.getElementById('custPaymentStatus').value = '';
        document.querySelectorAll('#paymentOptions .payment-option').forEach(o => o.classList.remove('selected'));
        document.querySelectorAll('#orderTypeToggle .type-option').forEach(o => o.classList.remove('selected'));
        document.querySelector('#orderTypeToggle .type-option[data-type="Delivery"]').classList.add('selected');
        document.getElementById('addressGroup').style.display = '';
        document.getElementById('detailsTitle').textContent = 'DELIVERY DETAILS';
        document.getElementById('custAddress').placeholder = 'Delivery address';
        document.getElementById('receiptOverlay').classList.remove('open');

        if (wasDelivery) {
            window.location.href = '/deliveries';
        } else {
            window.location.reload();
        }
    } catch (err) {
        console.error('POS sale failed:', err);
        toast('Could not reach the server. Please try again.', 'error');
        triggerBtn.disabled = false;
    }
}

document.getElementById('confirmDeliveryBtn').addEventListener('click', function(){
    submitSale(this);
});

document.getElementById('confirmPickupBtn').addEventListener('click', function(){
    submitSale(this);
});

/* ---------------- PRINTER CONNECTION ---------------- */
document.addEventListener('DOMContentLoaded', async () => {
    const statusEl = document.getElementById('printer-status');
    const connectBtn = document.getElementById('connect-printer-btn');
    if (!statusEl || !connectBtn) return;

    function renderPrinterStatus(status) {
        if (status === 'connected') {
            statusEl.textContent = 'Printer: PT210 Connected';
            statusEl.classList.add('connected');
            connectBtn.style.display = 'none';
        } else {
            statusEl.textContent = 'Printer: Not Connected';
            statusEl.classList.remove('connected');
            connectBtn.style.display = 'inline-block';
        }
    }

    if (!window.pt210 || !window.pt210.isSupported()) {
        statusEl.textContent = 'Printer: Unsupported browser (use Chrome/Edge)';
        connectBtn.style.display = 'none';
        return;
    }

    window.pt210.onStatusChange = renderPrinterStatus;

    const reconnected = await window.pt210.tryReconnect();
    renderPrinterStatus(reconnected ? 'connected' : 'disconnected');

    connectBtn.addEventListener('click', async () => {
        try {
            await window.pt210.connect();
            toast('Printer connected.', 'success');
        } catch (err) {
            console.error('Printer connection cancelled or failed:', err);
            toast('Printer connection cancelled or failed.', 'error');
        }
    });
});

/* ---------------- INIT ---------------- */
renderProducts();
renderCart();

document.getElementById('testPrintBtn')?.addEventListener('click', async () => {
    const result = await printReceipt(buildPrintPayload({
        customer: 'Test Customer',
        contact: '0900-000-0000',
        orderType: 'Pickup',
        address: 'Pickup at store',
        notes: '',
        payment: 'Cash',
        paymentStatus: 'Paid',
        items: [{ name: 'Test Item', qty: 1, price: 100 }],
        total: 100,
        date: formatReceiptDate(),
    }));
    console.log('Print result:', result);
    toast(result.status === 'printed' ? 'Printed!' : 'Failed: ' + result.message, result.status === 'printed' ? 'success' : 'error');
});