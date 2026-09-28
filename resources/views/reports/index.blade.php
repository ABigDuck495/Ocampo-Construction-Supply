    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reports - Ocampo Construction and Hardware Supplies</title>
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

    <!-- Shared stylesheets (same design system as POS / Delivery Ops) -->
    @vite(['resources/css/deliveries.css', 'resources/css/sidebar.css', 'resources/css/reports.css'])
    @include('partials.system_settings_js')
    </head>
    <body>

    @include('partials.sidebar')

    <!-- ============================================================
        MAIN CONTENT (REPORTS)
        ============================================================ -->
    <main class="main">
        <div class="page-toolbar">
        <div class="header">
            <div>
                <h1>REPORTS</h1>
                <p>Daily summaries with detailed drill-downs</p>
            </div>
            <div class="header-stats" id="headerStats">
                <div class="header-month-picker">
                    <select id="headerMonth"></select>
                    <select id="headerYear"></select>
                </div>
                <div class="hstat"><b id="statTotalRevenue">₱0.00</b><span>REVENUE</span></div>
                <div class="hstat"><b id="statTotalOrders">0</b><span>ORDERS</span></div>
                <div class="hstat"><b id="statAvgOrder">₱0.00</b><span>AVG ORDER</span></div>
            </div>
        </div>

        <div class="tabs" id="reportTabs">
            <div class="tab active" data-tab="sales">SALES SUMMARY</div>
            <div class="tab" data-tab="delivery">DELIVERY HISTORY</div>
        </div>
        </div>

        <div class="report-view active" id="view-sales">
            <div class="filter-bar" id="filterBar">
                <div class="filter-field">
                    <label>Report Type</label>
                    <select id="filterReportType">
                        <option value="orders" selected>Customer Orders</option>
                        <option value="items">Items Ordered</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Month</label>
                    <select id="filterMonth">
                        <option value="1">January</option>
                        <option value="2">February</option>
                        <option value="3">March</option>
                        <option value="4">April</option>
                        <option value="5">May</option>
                        <option value="6">June</option>
                        <option value="7">July</option>
                        <option value="8">August</option>
                        <option value="9">September</option>
                        <option value="10">October</option>
                        <option value="11">November</option>
                        <option value="12">December</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Year</label>
                    <select id="filterYear"></select>
                </div>
                <div class="filter-actions">
                    <button type="button" id="btnGenerateReport" class="btn-primary">Generate Report</button>
                    <button type="button" id="btnExportPdf" class="btn-secondary">Export PDF</button>
                    <button type="button" id="btnExportCsv" class="btn-secondary">Export CSV</button>
                </div>
            </div>

            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-card-label">TOTAL REVENUE</div>
                    <div class="stat-card-value orange" id="cardRevenue">₱0.00</div>
                    <div class="stat-card-sub" id="cardRevenueRange">—</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">TOTAL ORDERS</div>
                    <div class="stat-card-value" id="cardOrders">0</div>
                    <div class="stat-card-sub" id="cardOrdersRange">—</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-label">AVG ORDER VALUE</div>
                    <div class="stat-card-value blue" id="cardAvg">₱0.00</div>
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

                <div class="panel-box" id="panel-recentSales" data-report-panel="orders">
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

                <div class="panel-box" id="panel-itemsOrdered" data-report-panel="items" style="display: none;">
                    <div class="panel-box-title">ITEMS ORDERED</div>
                    <div class="data-table-wrap">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>PRODUCT</th>
                                    <th>CATEGORY</th>
                                    <th>UNITS SOLD</th>
                                    <th>REVENUE</th>
                                </tr>
                            </thead>
                            <tbody id="itemsOrderedBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="report-summary-list" id="reportSummaryList"></div>
        </div>

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