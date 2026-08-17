<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>POS - Ocampo Construction and Hardware Supplies</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Press+Start+2P&display=swap" rel="stylesheet">
<script>
    window.POS_DATA = {
        products: @json($products),
    };
</script>
<meta name="csrf-token" content="{{ csrf_token() }}">

<!-- <button id="testPrintBtn" style="position:fixed; bottom:20px; right:20px; z-index:9999; padding:10px 16px; background:#222; color:#fff; border:none; border-radius:6px; cursor:pointer;">
    TEST PRINT
</button> -->

@vite(['resources/js/pages/sidebar.js', 'resources/js/pages/pos.js'])
<!-- Shared stylesheets (same design system as Delivery Ops) -->
@vite(['resources/css/deliveries.css', 'resources/css/sidebar.css', 'resources/css/pos.css'])
</head>
<body>

<!-- ============================================================
     SIDEBAR (identical to Delivery Ops - POS tab active here)
     ============================================================ -->
<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
        </div>
        <div>
            <div class="brand-name">Ocampo Construction and Hardware Supplies</div>
            <div class="brand-sub">POS</div>
        </div>
    </div>
    <nav class="nav">
        <a href="{{ route('pos.index') }}" class="nav-item active"><span class="lbl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="14" rx="1"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg>
            POS</span></a>
        <a href="{{ route('deliveries.index') }}" class="nav-item"><span class="lbl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v10H3z"/><path d="M14 10h4l3 3v4h-7z"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="17.5" cy="19" r="1.5"/></svg>
            DELIVERY</span><span class="nav-badge" id="sidebarBadge">5</span></a>
        <a href="{{ route('inventory.index') }}" class="nav-item"><span class="lbl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v4H3z"/><path d="M5 7v13a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>
            INVENTORY</span></a>
        <a href="{{ route('reports.index') }}" class="nav-item"><span class="lbl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
            REPORTS</span></a>
        <a href="{{ route('users.index') }}" class="nav-item"><span class="lbl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17.5" cy="9" r="2.5"/><path d="M15 20a5 5 0 0 1 8 0"/></svg>
            USERS</span></a>
    </nav>

    <div class="sidebar-footer">
        <button class="theme-toggle" id="themeToggle" type="button" aria-label="Toggle light/dark mode">
            <span class="theme-toggle-icon" id="themeIcon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
            </span>
            <span class="theme-toggle-label" id="themeLabel">DARK MODE</span>
        </button>

        <a href="{{ url('/login') }}" class="signout-btn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/></svg>
            SIGN OUT
        </a>
    </div>
</aside>

<!-- ============================================================
     MAIN CONTENT (POS)
     ============================================================ -->
<main class="main">
    <div class="header">
        <div>
            <h1>POINT OF SALE</h1>
            <p id="headerSub">Ring up an order, print the receipt, send it to delivery</p>
        </div>
        <div class="printer-status-widget">
            <span id="printer-status">Printer: Checking...</span>
            <button id="connect-printer-btn" type="button" style="display:none;">Connect Printer</button>
        </div>
        <div class="header-stats">
            <div class="hstat"><b id="statCartItems">0</b><span>ITEMS</span></div>
            <div class="hstat"><b id="statCartTotal">$0.00</b><span>TOTAL</span></div>
        </div>
    </div>

    <div class="tabs" id="categoryTabs">
        <div class="tab active" data-cat="all">ALL</div>
        <div class="tab" data-cat="Tools">TOOLS</div>
        <div class="tab" data-cat="Electrical">ELECTRICAL</div>
        <div class="tab" data-cat="Plumbing">PLUMBING</div>
        <div class="tab" data-cat="Paint">PAINT</div>
        <div class="tab" data-cat="Hardware">HARDWARE</div>
    </div>
    <div class="hint">&middot; Click a product to add it to the cart &middot;</div>

    <div class="pos-board">
        <div class="product-grid" id="productGrid"></div>

        <div class="cart-panel">
            <div class="cart-head">
                <div class="cart-title">CURRENT ORDER</div>
                <button class="btn-ghost" id="clearCartBtn">CLEAR</button>
            </div>

            <div class="cart-items" id="cartItems"></div>

            <div class="cart-summary">
                <div class="cart-row"><span>Subtotal</span><span id="cartSubtotal">$0.00</span></div>
                <div class="cart-row total"><span>Total</span><span id="cartTotal">$0.00</span></div>
            </div>

            <div class="order-type-toggle" id="orderTypeToggle">
                <button type="button" class="type-option selected" data-type="Delivery">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v10H3z"/><path d="M14 10h4l3 3v4h-7z"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="17.5" cy="19" r="1.5"/></svg>
                    <span>DELIVERY</span>
                </button>
                <button type="button" class="type-option" data-type="Pickup">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7h-3V6a4 4 0 0 0-8 0v1H6a1 1 0 0 0-1 1v11a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8a1 1 0 0 0-1-1z"/><path d="M9 11v2a3 3 0 0 0 6 0v-2"/></svg>
                    <span>PICKUP</span>
                </button>
            </div>

            <div class="customer-form">
                <div class="cf-title" id="detailsTitle">DELIVERY DETAILS</div>
                <input type="text" id="custName" placeholder="Customer name" class="cf-input">
                <input type="tel" id="custContact" placeholder="Contact number" class="cf-input">
                <div id="addressGroup">
                    <input type="text" id="custAddress" placeholder="Delivery address" class="cf-input">
                </div>
                <textarea id="custNotes" placeholder="Notes (optional)" class="cf-input cf-textarea" rows="2"></textarea>
                <select id="custPaymentStatus" class="cf-input">
                    <option value="">Payment status</option>
                    <option value="Paid">Paid</option>
                    <option value="Unpaid">Unpaid</option>
                </select>
            </div>

            <div class="payment-form">
                <div class="cf-title">PAYMENT METHOD</div>
                <div class="payment-options" id="paymentOptions">
                    <button type="button" class="payment-option" data-payment="COD">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 10v.01M18 14v.01"/></svg>
                        <span>COD</span>
                    </button>
                    <button type="button" class="payment-option" data-payment="GCash">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="2" width="14" height="20" rx="2"/><path d="M9 18h6"/></svg>
                        <span>GCash</span>
                    </button>
                    <button type="button" class="payment-option" data-payment="Card">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
                        <span>Card</span>
                    </button>
                    <button type="button" class="payment-option" data-payment="Bank Transfer">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 10l9-6 9 6"/><path d="M4 10v9M9 10v9M15 10v9M20 10v9"/><path d="M2 21h20"/></svg>
                        <span>Bank Transfer</span>
                    </button>
                </div>
            </div>

            <button class="btn btn-checkout" id="checkoutBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                CHECKOUT
            </button>
        </div>
    </div>
</main>

<!-- ============================================================
     RECEIPT MODAL
     ============================================================ -->
<div class="receipt-overlay" id="receiptOverlay">
    <div class="receipt-modal">
        <div class="receipt-paper" id="receiptPaper">
            <!-- filled in by pos.js -->
        </div>
        <div class="receipt-actions">
            <button class="btn btn-cancel" id="receiptBackBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14l-4-4 4-4"/><path d="M5 10h11a4 4 0 0 1 0 8h-1"/></svg>
                BACK
            </button>
            <button class="btn btn-print" id="printBtn">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/></svg>
                PRINT
            </button>
            <button class="btn btn-confirm" id="confirmDeliveryBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            CONFIRM &amp; SEND TO DELIVERY
            </button>
            <button class="btn btn-confirm" id="confirmPickupBtn">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6L9 17l-5-5"/></svg>
            CONFIRM PICKUP
            </button>
        </div>
    </div>
</div>

<!-- Shared + page scripts -->
@vite(['resources/js/pages/sidebar.js', 'resources/js/pages/pos.js'])
</body>
</html>