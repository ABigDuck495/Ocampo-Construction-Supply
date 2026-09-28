<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard - Ocampo Construction and Hardware Supplies</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Press+Start+2P&display=swap" rel="stylesheet">
<script>
    (function() {
        try {
            if (localStorage.getItem('theme') === 'light') {
                document.documentElement.classList.add('light-mode-pending');
            }
        } catch (e) {}
    })();
</script>
@include('partials.system_settings_js')
<meta name="csrf-token" content="{{ csrf_token() }}">

@vite(['resources/js/pages/sidebar.js', 'resources/js/pages/dashboard.js'])
<!-- Shared stylesheets (same design system as POS / Delivery Ops) -->
@vite(['resources/css/deliveries.css', 'resources/css/sidebar.css', 'resources/css/dashboard.css'])
</head>
<body>
@include('partials.sidebar')
@php
    // Safe defaults so the page renders even before the controller passes data.
    $todayRevenue    = $todayRevenue    ?? 0;
    $revenueDelta    = $revenueDelta    ?? null;   // % vs yesterday (nullable)
    $ordersToday     = $ordersToday     ?? 0;
    $pendingCount    = $pendingCount    ?? 0;
    $trucksEnRoute   = $trucksEnRoute   ?? 0;
    $totalTrucks     = $totalTrucks     ?? 0;
    $lowStockCount   = $lowStockCount   ?? 0;
    $pendingOrders   = $pendingOrders   ?? collect();
    $lowStockItems   = $lowStockItems   ?? collect();
@endphp


<main class="dash">

    <!-- ===== HERO BANNER ===== -->
    <div class="dash-toolbar">
    <header class="dash-hero">
        <div class="dash-hero-left">
            <h1 class="dash-title">DASHBOARD</h1>
            <p class="dash-sub">Here's how the shop is doing today. Ring up, dispatch, restock.</p>
        </div>
        <div class="dash-hero-right">
            <div class="dash-clock" id="dashClock">--:--</div>
            <div class="dash-date" id="dashDate">&nbsp;</div>
        </div>
    </header>
    </div>

    <!-- ===== STAT CARDS ===== -->
    <section class="stat-grid">

        <div class="stat-card stat-revenue">
            <div class="stat-head">
                <span class="stat-label">TODAY'S REVENUE</span>
                <span class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
                </span>
            </div>
            <div class="stat-value">₱<span data-count="{{ $todayRevenue }}" data-decimals="2">{{ number_format($todayRevenue, 2) }}</span></div>
            <div class="stat-foot">
                @if(!is_null($revenueDelta))
                    <span class="delta {{ $revenueDelta >= 0 ? 'up' : 'down' }}">{{ $revenueDelta >= 0 ? '▲' : '▼' }} {{ abs($revenueDelta) }}%</span> vs yesterday
                @else
                    {{ $ordersToday }} {{ Str::plural('order', $ordersToday) }} today
                @endif
            </div>
        </div>

        <a href="{{ route('deliveries.index') }}" class="stat-card stat-pending">
            <div class="stat-head">
                <span class="stat-label">PENDING ORDERS</span>
                <span class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
            </div>
            <div class="stat-value"><span data-count="{{ $pendingCount }}" data-decimals="0">{{ $pendingCount }}</span></div>
            <div class="stat-foot">Waiting to be dispatched</div>
        </a>

        <a href="{{ route('deliveries.index') }}" class="stat-card stat-trucks">
            <div class="stat-head">
                <span class="stat-label">TRUCKS EN ROUTE</span>
                <span class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v10H3z"/><path d="M14 10h4l3 3v4h-7z"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="17.5" cy="19" r="1.5"/></svg>
                </span>
            </div>
            <div class="stat-value"><span data-count="{{ $trucksEnRoute }}" data-decimals="0">{{ $trucksEnRoute }}</span><small>/ {{ $totalTrucks }}</small></div>
            <div class="stat-foot">Out on delivery right now</div>
        </a>

        <a href="{{ route('inventory.index') }}" class="stat-card stat-stock {{ $lowStockCount > 0 ? 'is-alert' : '' }}">
            <div class="stat-head">
                <span class="stat-label">LOW ON STOCK</span>
                <span class="stat-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l10 18H2z"/><path d="M12 10v5"/><path d="M12 18v.01"/></svg>
                </span>
            </div>
            <div class="stat-value"><span data-count="{{ $lowStockCount }}" data-decimals="0">{{ $lowStockCount }}</span></div>
            <div class="stat-foot">{{ $lowStockCount > 0 ? 'Items need restocking' : 'All shelves look good' }}</div>
        </a>

    </section>

    <!-- ===== QUICK ACCESS ===== -->
    <div class="dash-section-label">· QUICK ACCESS ·</div>
    <section class="quick-grid">

        <a href="{{ route('pos.index') }}" class="quick-tile">
            <span class="quick-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="14" rx="1"/><path d="M8 7V5a4 4 0 0 1 8 0v2"/></svg>
            </span>
            <span class="quick-name">POS</span>
            <span class="quick-desc">Ring up a new order</span>
            <span class="quick-go">OPEN →</span>
        </a>

        <a href="{{ route('deliveries.index') }}" class="quick-tile">
            <span class="quick-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7h11v10H3z"/><path d="M14 10h4l3 3v4h-7z"/><circle cx="7.5" cy="19" r="1.5"/><circle cx="17.5" cy="19" r="1.5"/></svg>
            </span>
            <span class="quick-name">DELIVERIES</span>
            <span class="quick-desc">Dispatch trucks & track orders</span>
            <span class="quick-go">OPEN →</span>
        </a>

        <a href="{{ route('inventory.index') }}" class="quick-tile">
            <span class="quick-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3h18v4H3z"/><path d="M5 7v13a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V7"/><path d="M9 12h6"/><path d="M9 16h6"/></svg>
            </span>
            <span class="quick-name">INVENTORY</span>
            <span class="quick-desc">Stock levels & movements</span>
            <span class="quick-go">OPEN →</span>
        </a>

        <a href="{{ route('reports.index') }}" class="quick-tile">
            <span class="quick-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
            </span>
            <span class="quick-name">REPORTS</span>
            <span class="quick-desc">Sales & delivery history</span>
            <span class="quick-go">OPEN →</span>
        </a>

        <a href="{{ route('users.index') }}" class="quick-tile">
            <span class="quick-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0"/><circle cx="17.5" cy="9" r="2.5"/><path d="M15 20a5 5 0 0 1 8 0"/></svg>
            </span>
            <span class="quick-name">USERS</span>
            <span class="quick-desc">Staff accounts & roles</span>
            <span class="quick-go">OPEN →</span>
        </a>

    </section>

    <!-- ===== WATCHLISTS ===== -->
    <section class="watch-grid">

        <div class="panel">
            <div class="panel-head">
                <h2 class="panel-title">PENDING ORDERS</h2>
                <a href="{{ route('deliveries.index') }}" class="panel-link">VIEW ALL</a>
            </div>
            <div class="panel-body">
                @forelse($pendingOrders as $order)
                    <div class="row-item">
                        <div class="row-main">
                            <div class="row-title">#{{ $order->id }} · {{ $order->customer_name ?? 'Walk-in' }}</div>
                            <div class="row-meta">{{ optional($order->created_at)->diffForHumans() }}</div>
                        </div>
                        <div class="row-amount">₱{{ number_format($order->total ?? 0, 2) }}</div>
                    </div>
                @empty
                    <div class="empty">NO PENDING ORDERS</div>
                @endforelse
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2 class="panel-title">LOW STOCK ALERTS</h2>
                <a href="{{ route('inventory.index') }}" class="panel-link">VIEW ALL</a>
            </div>
            <div class="panel-body">
                @forelse($lowStockItems as $item)
                    @php
                        $min = max((int) ($item->min_stock ?? 0), 1);
                        $pct = min(100, round(((int) ($item->stock ?? 0) / $min) * 100));
                    @endphp
                    <div class="row-item">
                        <div class="row-main">
                            <div class="row-title">{{ $item->name }}</div>
                            <div class="stock-bar"><span style="width: {{ $pct }}%"></span></div>
                        </div>
                        <div class="row-amount stock-qty {{ ($item->stock ?? 0) <= 0 ? 'out' : '' }}">
                            {{ ($item->stock ?? 0) <= 0 ? 'OUT' : $item->stock . ' left' }}
                        </div>
                    </div>
                @empty
                    <div class="empty">ALL STOCK LEVELS OK</div>
                @endforelse
            </div>
        </div>

    </section>

</main>

</body>
</html>