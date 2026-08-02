/* ============================================================
   POS - DATA + LOGIC
   ============================================================ */

/* ---------------- CATALOG ---------------- */
const products = [
    { id:'P01', name:'Titanium Hammer',      cat:'Tools',      price:12.50 },
    { id:'P02', name:'Cordless Drill 18V',   cat:'Tools',      price:54.00 },
    { id:'P03', name:'Paint Roller Set',     cat:'Paint',      price:6.40  },
    { id:'P04', name:'Latex Paint 1L',       cat:'Paint',      price:5.20  },
    { id:'P05', name:'Hex Bolt M8 x50',      cat:'Hardware',   price:3.20  },
    { id:'P06', name:'Steel Nails 1kg',      cat:'Hardware',   price:3.15  },
    { id:'P07', name:'Safety Gloves L',      cat:'Hardware',   price:3.15  },
    { id:'P08', name:'PVC Pipe 3/4"',        cat:'Plumbing',   price:4.10  },
    { id:'P09', name:'PVC Elbow Joint',      cat:'Plumbing',   price:1.80  },
    { id:'P10', name:'Circuit Breaker 20A',  cat:'Electrical', price:9.75  },
    { id:'P11', name:'Electrical Wire 10m',  cat:'Electrical', price:7.90  },
    { id:'P12', name:'LED Bulb 9W',          cat:'Electrical', price:2.60  },
];

const icons = {
    Tools:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>',
    Paint:      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 11H7a2 2 0 0 0-2 2v7a2 2 0 0 0 2 2h5"/><path d="M9 11V7a3 3 0 0 1 6 0v1"/><circle cx="18" cy="18" r="3"/></svg>',
    Hardware:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
    Plumbing:   '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h6v6H4z"/><path d="M14 14h6v6h-6z"/><path d="M10 7h4v7h-4z"/></svg>',
    Electrical: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h7v8l10-12h-7z"/></svg>',
};

/* ---------------- STATE ---------------- */
let cart = [];        // [{ id, name, price, qty }]
let activeCat = 'all';
let selectedPayment = null;
let orderType = 'Delivery';

function fmt(n){ return '$' + n.toFixed(2); }

/* ---------------- RENDER: PRODUCTS ---------------- */
function renderProducts(){
    const grid = document.getElementById('productGrid');
    const list = activeCat === 'all' ? products : products.filter(p => p.cat === activeCat);

    grid.innerHTML = list.map(p => `
        <div class="product-card" data-id="${p.id}">
            <div class="product-icon">${icons[p.cat]}</div>
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
    const product = products.find(p => p.id === productId);
    if(!product) return;
    const existing = cart.find(c => c.id === productId);
    if(existing){ existing.qty += 1; }
    else { cart.push({ id: product.id, name: product.name, price: product.price, qty: 1 }); }
    renderCart();
}

function changeQty(productId, delta){
    const item = cart.find(c => c.id === productId);
    if(!item) return;
    item.qty += delta;
    if(item.qty <= 0) cart = cart.filter(c => c.id !== productId);
    renderCart();
}

function removeFromCart(productId){
    cart = cart.filter(c => c.id !== productId);
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
function generateOrderId(){
    const n = Math.floor(100000 + Math.random() * 900000);
    return `RC-${n}`;
}

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
        id: generateOrderId(),
        customer: name,
        contact: contact,
        address: isPickup ? 'Pickup at store' : address,
        notes: notes,
        orderType: orderType,
        items: cart.map(i => ({ name: i.name, qty: i.qty })),
        total: cartTotal(),
        payment: selectedPayment,
        paymentStatus: paymentStatus,
        status: 'pending',
        truck: null,
    };

    renderReceipt(pendingOrder);
    document.getElementById('receiptOverlay').classList.add('open');
});

function renderReceipt(order){
    const paper = document.getElementById('receiptPaper');
    const now = new Date().toLocaleString('en-US', { month:'short', day:'numeric', year:'numeric', hour:'numeric', minute:'2-digit' });

    paper.innerHTML = `
        <h2>IRONCLAD HARDWARE</h2>
        <div class="r-sub">Ocampo Construction &amp; Hardware Supplies</div>
        <div class="r-meta">Order: ${order.id}</div>
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

/* Confirm & Send to Delivery:
   Stores the order for Delivery Ops to pick up, then redirects. */
document.getElementById('confirmBtn').addEventListener('click', () => {
    if(!pendingOrder) return;

    try{
        const existing = JSON.parse(localStorage.getItem('pendingDeliveryOrders') || '[]');
        existing.push(pendingOrder);
        localStorage.setItem('pendingDeliveryOrders', JSON.stringify(existing));
    } catch(err){
        console.error('Could not queue order for delivery:', err);
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

    // hand off to Delivery Ops
    window.location.href = "{{ url('/deliveries') }}";
});

/* ---------------- INIT ---------------- */
renderProducts();
renderCart();
