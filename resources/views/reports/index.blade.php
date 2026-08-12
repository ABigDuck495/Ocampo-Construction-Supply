<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reports - Ocampo Construction and Hardware Supplies</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Press+Start+2P&display=swap" rel="stylesheet">

<!-- Shared stylesheets (same design system as POS / Delivery Ops) -->
@vite(['resources/css/deliveries.css', 'resources/css/sidebar.css', 'resources/css/reports.css'])
</head>
<body>

<!-- ============================================================
     SIDEBAR (identical to other pages - Reports tab active here)
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
        <a href="{{ route('pos.index') }}" class="nav-item"><span class="lbl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="14" rx="1"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg>
            POS</span></a>
        <a href="{{ route('deliveries.index') }}" class="nav-item"><span class="lbl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v10H3z"/><path d="M14 10h4l3 3v4h-7z"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="17.5" cy="19" r="1.5"/></svg>
            DELIVERY</span><span class="nav-badge" id="sidebarBadge">5</span></a>
        <a href="{{ route('inventory.index') }}" class="nav-item"><span class="lbl">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v4H3z"/><path d="M5 7v13a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>
            INVENTORY</span></a>
        <a href="{{ route('reports.index') }}" class="nav-item active"><span class="lbl">
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
     MAIN CONTENT (REPORTS)
     ============================================================ -->
<main class="main">
    <div class="header">
        <div>
            <h1>REPORTS</h1>
            <p>Sales performance &amp; delivery history</p>
        </div>
        <div class="header-stats">
            <div class="hstat"><b id="statTotalRevenue">$0.00</b><span>REVENUE</span></div>
            <div class="hstat"><b id="statTotalOrders">0</b><span>ORDERS</span></div>
            <div class="hstat"><b id="statAvgOrder">$0.00</b><span>AVG ORDER</span></div>
        </div>
    </div>

    <div class="tabs" id="reportTabs">
        <div class="tab active" data-tab="sales">SALES SUMMARY</div>
        <div class="tab" data-tab="delivery">DELIVERY HISTORY</div>
    </div>

    <!-- ---------- SALES SUMMARY VIEW ---------- -->
    <div class="report-view active" id="view-sales">
        <div class="stat-cards">
            <div class="stat-card">
                <div class="stat-card-label">TOTAL REVENUE</div>
                <div class="stat-card-value orange" id="cardRevenue">$0.00</div>
                <div class="stat-card-sub">Last 30 days</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">TOTAL ORDERS</div>
                <div class="stat-card-value" id="cardOrders">0</div>
                <div class="stat-card-sub">Last 30 days</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">AVG ORDER VALUE</div>
                <div class="stat-card-value blue" id="cardAvg">$0.00</div>
                <div class="stat-card-sub">Per transaction</div>
            </div>
            <div class="stat-card">
                <div class="stat-card-label">TOP CATEGORY</div>
                <div class="stat-card-value green" id="cardCategory">—</div>
                <div class="stat-card-sub">By units sold</div>
            </div>
        </div>

        <div class="sales-grid">
            <div class="panel-box">
                <div class="panel-box-title">TOP PRODUCTS</div>
                <div id="topProductsList"></div>
            </div>

            <div class="panel-box">
                <div class="panel-box-title">RECENT SALES</div>
                <div class="data-table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ORDER</th>
                                <th>CUSTOMER</th>
                                <th>ITEMS</th>
                                <th>TOTAL</th>
                                <th>PAYMENT</th>
                                <th>DATE</th>
                            </tr>
                        </thead>
                        <tbody id="recentSalesBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- ---------- DELIVERY HISTORY VIEW ---------- -->
    <div class="report-view" id="view-delivery">
        <div class="panel-box">
            <div class="panel-box-title">DELIVERY HISTORY</div>
            <div class="data-table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ORDER</th>
                            <th>CUSTOMER</th>
                            <th>TRUCK / DRIVER</th>
                            <th>STATUS</th>
                            <th>DISPATCHED</th>
                            <th>DELIVERED</th>
                            <th>PAYMENT</th>
                        </tr>
                    </thead>
                    <tbody id="deliveryHistoryBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Shared + page scripts -->
@vite(['resources/js/pages/sidebar.js', 'resources/js/pages/reports.js'])
</body>
</html>