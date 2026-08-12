    <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory - Ironclad Hardware</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Press+Start+2P&display=swap" rel="stylesheet">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        window.INVENTORY_DATA = {
            inventories: @json($inventories),
            lowStockCount: {{ $lowStockCount }},
            outOfStockCount: {{ $outOfStockCount }}
        };
    </script>

    <!-- sidebar.css loads BEFORE the page-specific stylesheet, same as reports.blade,
        so inventory.css can safely override without fighting the shared .main rules -->
    @vite(['resources/css/sidebar.css', 'resources/css/inventory.css'])
    </head>
    <body>

    <!-- ============================================================
        SIDEBAR (identical to other pages - Inventory tab active here)
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
            <a href="{{ route('inventory.index') }}" class="nav-item active"><span class="lbl">
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
        MAIN CONTENT (INVENTORY / PRODUCT CATALOG)
        ============================================================ -->
    <main class="main">
        <div class="header">
            <div>
                <h1>PRODUCT CATALOG</h1>
                <p id="headerSub"></p>
            </div>
            <div class="header-stats">
                <div class="hstat"><b id="statTotal">0</b><span>TOTAL PRODUCTS</span></div>
                <div class="hstat value"><b id="statValue">$0.00</b><span>STOCK VALUE</span></div>
                <div class="hstat low"><b id="statLow">0</b><span>LOW STOCK</span></div>
                <div class="hstat out"><b id="statOut">0</b><span>OUT OF STOCK</span></div>
            </div>
            <button class="add-product-btn" id="addProductBtn" type="button">+ ADD PRODUCT</button>
        </div>

        <div class="search-bar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="6 3 20 12 6 21 6 3"/></svg>
            <input type="text" id="searchInput" placeholder="SEARCH NAME, SKU, CATEGORY...">
        </div>

        <div class="tabs" id="catTabs">
            <div class="tab active" data-cat="all">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 17l6-6-6-6"/><path d="M12 19h8"/></svg>
                All</div>
            <div class="tab" data-cat="Tools">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                Tools</div>
            <div class="tab" data-cat="Power Tools">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                Power Tools</div>
            <div class="tab" data-cat="Plumbing">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h4v6H6z"/><path d="M8 9v4a4 4 0 0 0 4 4h2"/><path d="M14 15h6v6h-6z"/></svg>
                Plumbing</div>
            <div class="tab" data-cat="Fasteners">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M4.2 6.2l2.1 2.1M17.7 15.7l2.1 2.1M3 12h3M18 12h3M4.2 17.8l2.1-2.1M17.7 8.3l2.1-2.1"/></svg>
                Fasteners</div>
            <div class="tab" data-cat="Electrical">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h7l-1 8 10-12h-7z"/></svg>
                Electrical</div>
            <div class="tab" data-cat="Paint">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a7 7 0 0 0 7-7c0-3-3-5-3-9a4 4 0 0 0-8 0c0 4-3 6-3 9a7 7 0 0 0 7 7z"/></svg>
                Paint</div>
            <div class="tab" data-cat="Safety">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5.5 3.5 9 8 11 4.5-2 8-5.5 8-11V5l-8-3z"/></svg>
                Safety</div>
        </div>

        <table class="product-table">
            <thead>
                <tr>
                    <th class="col-product">PRODUCT</th>
                    <th>SKU</th>
                    <th>CATEGORY</th>
                    <th>PRICE</th>
                    <th>STOCK</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody id="productBody"></tbody>
        </table>
    </main>

    <!-- ============================================================
        ADD PRODUCT MODAL
        ============================================================ -->
    <div class="modal-overlay" id="addProductModal">
        <div class="modal-box">
            <div class="modal-head">
                <h2>ADD PRODUCT</h2>
                <button type="button" class="modal-close" id="modalCloseBtn" aria-label="Close">&times;</button>
            </div>
            <form id="addProductForm" data-store-url="{{ route('inventory.storeWithProduct') }}">
                <div class="modal-body">
                    <div class="form-row">
                        <label for="fldName">Product Name</label>
                        <input type="text" id="fldName" name="Product_Name" required>
                    </div>
                    <div class="form-row-split">
                        <div class="form-row">
                            <label for="fldSku">SKU (optional)</label>
                            <input type="text" id="fldSku" name="SKU">
                        </div>
                        <div class="form-row">
                            <label for="fldCategory">Category</label>
                            <select id="fldCategory" name="Category" required>
                                <option value="Tools">Tools</option>
                                <option value="Power Tools">Power Tools</option>
                                <option value="Plumbing">Plumbing</option>
                                <option value="Fasteners">Fasteners</option>
                                <option value="Electrical">Electrical</option>
                                <option value="Paint">Paint</option>
                                <option value="Safety">Safety</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <label for="fldSubCategory">Sub-Category</label>
                        <input type="text" id="fldSubCategory" name="SubCategory" placeholder="e.g. Cement, Pipes, Nails" required>
                    </div>
                    <div class="form-row-split">
                        <div class="form-row">
                            <label for="fldPrice">Price ($)</label>
                            <input type="number" id="fldPrice" name="Price" step="0.01" min="0" required>
                        </div>
                        <div class="form-row">
                            <label for="fldStock">Quantity On Hand</label>
                            <input type="number" id="fldStock" name="QuantityOnHand" min="0" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <label for="fldReorderLevel">Reorder Level (optional, defaults to 10)</label>
                        <input type="number" id="fldReorderLevel" name="ReorderLevel" min="0" placeholder="10">
                    </div>
                    <div class="form-error" id="modalFormError"></div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn-cancel" id="modalCancelBtn">CANCEL</button>
                    <button type="submit" class="btn-submit" id="modalSubmitBtn">SAVE PRODUCT</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Separated scripts -->
    @vite(['resources/js/pages/inventory.js', 'resources/js/pages/sidebar.js', 'resources/js/pages/inventory-add-product.js'])
    </body>
    </html>