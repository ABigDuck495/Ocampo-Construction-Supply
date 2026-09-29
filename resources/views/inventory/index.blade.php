<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <script>
        (function() {
            try {
                if (localStorage.getItem('theme') === 'light') {
                    document.documentElement.classList.add('light-mode-pending');
                }
            } catch (e) {}
        })();
    </script>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inventory - Ocampo Construction and Hardware Supplies</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700;800&family=Press+Start+2P&display=swap" rel="stylesheet">

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        window.INVENTORY_DATA = {
            inventories: @json($inventories),
            lowStockCount: {{ $lowStockCount }}
        };
    </script>

    @include('partials.system_settings_js')

    <!-- sidebar.css loads BEFORE the page-specific stylesheet, same as reports.blade,
         so inventory.css can safely override without fighting the shared .main rules -->
    @vite(['resources/css/deliveries.css', 'resources/css/sidebar.css', 'resources/css/inventory.css'])
</head>
<body>
    @include('partials.sidebar')

    <main class="main">
        <div class="page-toolbar">
            <div class="header">
                <div>
                    <h1>PRODUCT CATALOG</h1>
                    <p id="headerSub"></p>
                </div>
                <div class="header-stats">
                    <div class="hstat"><b id="statTotal">0</b><span>TOTAL PRODUCTS</span></div>
                    <div class="hstat value"><b id="statValue">₱0.00</b><span>STOCK VALUE</span></div>
                    <div class="hstat low"><b id="statLow">0</b><span>LOW STOCK</span></div>
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
                    All
                </div>
                <div class="tab" data-cat="Tools">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    Tools
                </div>
                <div class="tab" data-cat="Power Tools">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    Power Tools
                </div>
                <div class="tab" data-cat="Plumbing">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h4v6H6z"/><path d="M8 9v4a4 4 0 0 0 4 4h2"/><path d="M14 15h6v6h-6z"/></svg>
                    Plumbing
                </div>
                <div class="tab" data-cat="Fasteners">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M4.2 6.2l2.1 2.1M17.7 15.7l2.1 2.1M3 12h3M18 12h3M4.2 17.8l2.1-2.1M17.7 8.3l2.1-2.1"/></svg>
                    Fasteners
                </div>
                <div class="tab" data-cat="Electrical">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2 3 14h7l-1 8 10-12h-7z"/></svg>
                    Electrical
                </div>
                <div class="tab" data-cat="Paint">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22a7 7 0 0 0 7-7c0-3-3-5-3-9a4 4 0 0 0-8 0c0 4-3 6-3 9a7 7 0 0 0 7 7z"/></svg>
                    Paint
                </div>
                <div class="tab" data-cat="Safety">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2 4 5v6c0 5.5 3.5 9 8 11 4.5-2 8-5.5 8-11V5l-8-3z"/></svg>
                    Safety
                </div>
            </div>
        </div>

        <table class="product-table">
            <thead>
                <tr>
                    <th class="col-product">PRODUCT</th>
                    <th>SKU</th>
                    <th>CATEGORY</th>
                    <th>UNIT</th>
                    <th>PRICE</th>
                    <th>STOCK</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody id="productBody"></tbody>
        </table>
    </main>

    <!-- ===== ADD PRODUCT MODAL ===== -->
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

                    <div class="form-row-split">
                        <div class="form-row">
                            <label for="fldSubCategory">Sub-Category</label>
                            <input type="text" id="fldSubCategory" name="SubCategory" placeholder="e.g. Cement, Pipes, Nails" required>
                        </div>
                        <div class="form-row">
                            <label for="fldUnit">Unit (e.g. pcs, bag, litre)</label>
                            <input type="text" id="fldUnit" name="Unit" required>
                        </div>
                    </div>

                    <div class="form-row-split">
                        <div class="form-row">
                            <label for="fldPrice">Price (₱)</label>
                            <input type="number" id="fldPrice" name="Price" step="0.01" min="0">
                        </div>
                        <div class="form-row">
                            <label for="fldStock">Quantity On Hand</label>
                            <input type="number" id="fldStock" name="QuantityOnHand" min="0" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <label class="toggle-row" for="fldVariablePricing">
                            <input type="checkbox" id="fldVariablePricing" name="Pricing_type" value="Variable">
                            <span class="toggle-switch" aria-hidden="true"></span>
                            <span class="toggle-text">VARIABLE PRICE</span>
                            <span class="toggle-state" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="form-row">
                        <label for="fldReorderLevel">Reorder Level (low-stock alert point, defaults to 10)</label>
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

    <!-- ===== EDIT PRODUCT MODAL ===== -->
    <div class="modal-overlay" id="editProductModal">
        <div class="modal-box">
            <div class="modal-head">
                <h2>EDIT PRODUCT</h2>
                <button type="button" class="modal-close" id="editModalCloseBtn" aria-label="Close">&times;</button>
            </div>
            <form id="editProductForm" data-update-url-template="{{ route('inventory.updateWithProduct', ['inventory' => '__ID__']) }}">
                <div class="modal-body">
                    <div class="form-row">
                        <label for="editFldName">Product Name</label>
                        <input type="text" id="editFldName" name="Product_Name" required>
                    </div>
                        <div class="form-row">
                            <label for="editFldPrice">Price (₱)</label>
                            <input type="text" id="editFldPrice" name="Price">
                        </div>
                        <div class="form-row">
                            <label for="editFldReorderLevel">Reorder Level (low-stock alert point)</label>
                            <input type="number" id="editFldReorderLevel" name="ReorderLevel" min="0">
                        </div>
                    <div class="form-row">
                        <label for="editFldStockIncrease">Add to Current Stock</label>
                        <div>Available now: <strong id="editCurrentStock">0</strong></div>
                        <input type="number" id="editFldStockIncrease" min="0" step="1" value="0">
                        <small>Enter the quantity to add. Available stock cannot be reduced here.</small>
                    </div>
                    <div class="form-row">
                        <label class="toggle-row" for="editFldVariablePricing">
                            <input type="checkbox" id="editFldVariablePricing" name="Pricing_type" value="Variable">
                            <span class="toggle-switch" aria-hidden="true"></span>
                            <span class="toggle-text">VARIABLE PRICE</span>
                            <span class="toggle-state" aria-hidden="true"></span>
                        </label>
                    </div>

                    <div class="form-error" id="editModalFormError"></div>
                </div>
                <div class="modal-foot">
                    <button type="button" class="btn-cancel" id="editModalCancelBtn">CANCEL</button>
                    <button type="submit" class="btn-submit" id="editModalSubmitBtn">SAVE CHANGES</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Separated scripts -->
    @vite(['resources/js/pages/inventory.js', 'resources/js/pages/sidebar.js', 'resources/js/pages/inventory-add-product.js', 'resources/js/pages/inventory-edit-product.js'])
</body>
</html>