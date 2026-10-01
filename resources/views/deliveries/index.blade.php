<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Delivery Ops - Ocampo Construction and Hardware Supplies</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Press+Start+2P&display=swap" rel="stylesheet">

<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
    window.DISPATCH_DATA = { orders: @json($orders), trucks: @json($trucks) };
</script>

<script>
    (function() {
        try {
            if (localStorage.getItem('theme') === 'light') {
                document.documentElement.classList.add('light-mode-pending');
            }
        } catch (e) {}
    })();
</script>

<style>
    /* Constrains dynamically injected SVG icons inside the workspace */
    .delivery-workspace svg {
        width: 16px;
        height: 16px;
        display: inline-block;
        vertical-align: middle;
    }
</style>

@include('partials.system_settings_js')
@vite(['resources/css/deliveries.css', 'resources/css/deliveries-layout.css', 'resources/css/sidebar.css'])
</head>
<body>

@include('partials.sidebar')

<main class="main delivery-main">

    <!-- HEADER + FILTER TABS -->
    <div class="delivery-toolbar">
        <div class="header">
            <div>
                <h1>DELIVERY OPS</h1>
                <p id="headerSub"></p>
            </div>
            <div class="header-stats">
                <div class="hstat"><b id="statPending"></b><span>PENDING</span></div>
                <div class="hstat"><b id="statTransit"></b><span>TRANSIT</span></div>
                <div class="hstat"><b id="statDone"></b><span>DONE</span></div>
            </div>
        </div>

        <div class="tabs" id="tabs">
            <div class="tab active" data-tab="all">ALL (<span class="cnt-all"></span>)</div>
            <div class="tab" data-tab="pending">PENDING (<span class="cnt-pending"></span>)</div>
            <div class="tab" data-tab="transit">TRANSIT (<span class="cnt-transit"></span>)</div>
            <div class="tab" data-tab="assigned">PARTIAL (<span class="cnt-assigned"></span>)</div>
            <div class="tab" data-tab="delivered">DELIVERED (<span class="cnt-delivered"></span>)</div>
        </div>
    </div>

    <!-- WORKSPACE: Receipts | Dispatch Log  /  Active Fleet -->
    <div class="board delivery-workspace">

        <section class="ws-panel receipts-panel">
            <div class="ws-head">
                <div class="fleet-title">RECEIPTS</div>
                <div class="hint">&middot; Drag an order onto a truck &middot;</div>
            </div>
            <div class="ws-scroll">
                <div class="order-list" id="orderList"></div>
            </div>
        </section>

        <section class="ws-panel log-panel dispatch-log-col">
            <div class="ws-head">
                <div class="fleet-title">DISPATCH LOG</div>
            </div>
            <div class="log-tabs" id="logTabs">
                <div class="log-tab active" data-logtab="deliveries">DELIVERIES</div>
                <div class="log-tab" data-logtab="items">ITEMS</div>
            </div>
            <div class="ws-scroll">
                <div class="log-list" id="logList"></div>
            </div>
        </section>

        <section class="ws-panel fleet-panel">
            <div class="ws-head fleet-head">
                <div class="fleet-title">ACTIVE FLEET</div>
                <div class="ws-head-actions">
                    <button type="button" class="mini-btn" id="addTruckBtn">ADD TRUCK</button>
                </div>
                <div class="fleet-stats">
                    <div class="fstat idle"><b id="fIdle"></b><span>IDLE</span></div>
                    <div class="fstat loading"><b id="fLoading"></b><span>LOADING</span></div>
                    <div class="fstat transit"><b id="fTransit"></b><span>TRANSIT</span></div>
                    <div class="fstat delivered"><b id="fDelivered"></b><span>DELIVERED</span></div>
                </div>
            </div>
            <div class="ws-scroll">
                <div class="truck-grid" id="truckGrid"></div>
            </div>
        </section>

    </div>
</main>

@vite(['resources/js/pages/deliveries.js', 'resources/js/pages/dnd-autoscroll.js', 'resources/js/pages/sidebar.js'])
</body>
</html>