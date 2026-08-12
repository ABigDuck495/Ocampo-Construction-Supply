/* ============================================================
   POS - DATA + LOGIC (wired to backend)
   ============================================================ */

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

/* ---------------- CATALOG (from PosController@index) ---------------- */
// window.POS_DATA.products is injected by the Blade view:
// each item: { ProductID, Product_Name, Category, Price, inventory: { QuantityOnHand } }
const rawProducts = (window.POS_DATA && window.POS_DATA.products) || [];

const products = rawProducts.map(p => ({
    id: p.ProductID,
    name: p.Product_Name,
    cat: p.Category || 'Hardware', // falls back until every product has a Category set
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
let cart = [];        // [{ id, name, price, qty, stock }]
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
        alert(`Not enough stock for ${product.name}. Available: ${product.stock}`);
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
        alert(`Not enough stock for ${item.name}. Available: ${item.stock}`);
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

    if(!cart.length){ alert('Cart is empty.'); return; }
    if(!name || !contact || !paymentStatus){
        alert('Please fill in customer name, contact number, and payment status before checking out.');
        return;
    }
    if(!isPickup && !address){
        alert('Please fill in the delivery address before checking out.');
        return;
    }
    if(!selectedPayment){
        alert('Please select a payment method before checking out.');
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
    };

    renderReceipt(pendingOrder);

    // Show only the confirm button that matches this order's type —
    // a Pickup order should never see "SEND TO DELIVERY" and vice versa.
    const isDelivery = orderType === 'Delivery';
    document.getElementById('confirmDeliveryBtn').style.display = isDelivery ? '' : 'none';
    document.getElementById('confirmPickupBtn').style.display = isDelivery ? 'none' : '';

    document.getElementById('receiptOverlay').classList.add('open');
});

function renderReceipt(order){
    const paper = document.getElementById('receiptPaper');
    const now = new Date().toLocaleString('en-US', { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' });

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

document.getElementById('printBtn').addEventListener('click', () => {
    window.print();
});

/* ----------------------------------------------------------
   Shared sale-confirmation logic used by BOTH the Delivery and
   Pickup confirm buttons. The backend (TransactionController@posSale)
   already saves order items for every item regardless of order type,
   so both buttons post the exact same payload — the only difference
   between them is the label shown and where the browser goes
   afterward.
   ---------------------------------------------------------- */
async function submitSale(triggerBtn){
    if(!pendingOrder) return;

    triggerBtn.disabled = true;
    const wasDelivery = pendingOrder.orderType === 'Delivery';

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
            alert(err.message || 'Something went wrong processing the sale.');
            triggerBtn.disabled = false;
            return;
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

        // Only a Delivery sale should route to the deliveries board —
        // Pickup sales are already Completed/Fulfilled, so send the
        // cashier back to POS (refreshed) to ring up the next customer.
        if (wasDelivery) {
            window.location.href = '/deliveries';
        } else {
            window.location.reload();
        }
    } catch (err) {
        console.error('POS sale failed:', err);
        alert('Could not reach the server. Please try again.');
        triggerBtn.disabled = false;
    }
}

document.getElementById('confirmDeliveryBtn').addEventListener('click', function(){
    submitSale(this);
});

document.getElementById('confirmPickupBtn').addEventListener('click', function(){
    submitSale(this);
});

/* ---------------- INIT ---------------- */
renderProducts();
renderCart();