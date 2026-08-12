// resources/js/pages/inventory-edit-product.js
//
// Wires up the EDIT button on each inventory row + the Edit Product modal.
// Reads the clicked row's data-* attributes (written by inventory.js's
// renderTable / inventory-add-product.js's appendProductRow) to pre-fill
// the form — no extra fetch needed since that data is already on the page.
//
// PUTs to InventoryController@updateWithProduct, which updates the
// Product AND Inventory rows together in one DB transaction. The URL is
// built from the form's data-update-url-template attribute by swapping
// in the row's inventory ID for the "__ID__" placeholder.
//
// Response shape (Inventory model with product loaded):
// {
//   InventoryID, ProductID, QuantityOnHand, ReorderLevel, ...,
//   product: { ProductID, Product_Name, SKU, Category, SubCategory, Price, ... }
// }

(function () {
    const modal = document.getElementById('editProductModal');
    const closeBtn = document.getElementById('editModalCloseBtn');
    const cancelBtn = document.getElementById('editModalCancelBtn');
    const form = document.getElementById('editProductForm');
    const submitBtn = document.getElementById('editModalSubmitBtn');
    const errorBox = document.getElementById('editModalFormError');
    const productBody = document.getElementById('productBody');

    if (!modal || !form || !productBody) return; // page markup not present, bail quietly

    const URL_TEMPLATE = form.dataset.updateUrlTemplate;
    let activeInventoryId = null;

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function openModal(btn) {
        activeInventoryId = btn.dataset.inventoryId;

        document.getElementById('editFldName').value = btn.dataset.name || '';
        document.getElementById('editFldSku').value = btn.dataset.sku || '';
        document.getElementById('editFldCategory').value = btn.dataset.category || 'Tools';
        document.getElementById('editFldSubCategory').value = btn.dataset.subcategory || '';
        document.getElementById('editFldPrice').value = btn.dataset.price || '0';
        document.getElementById('editFldStock').value = btn.dataset.stock || '0';
        document.getElementById('editFldReorderLevel').value = btn.dataset.reorderLevel || '';

        errorBox.classList.remove('show');
        errorBox.textContent = '';
        modal.classList.add('open');
    }

    function closeModal() {
        modal.classList.remove('open');
        activeInventoryId = null;
    }

    productBody.addEventListener('click', (e) => {
        const editBtn = e.target.closest('.btn-edit');
        if (editBtn) openModal(editBtn);
    });

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });

    function updateRowInPlace(inventory) {
        const row = productBody.querySelector(`tr[data-inventory-id="${inventory.InventoryID}"]`);
        if (!row) return;

        const product = inventory.product;
        const qty = Number(inventory.QuantityOnHand);
        const reorderLevel = Number(inventory.ReorderLevel);
        const price = Number(product.Price);

        row.querySelector('.prod-cell span').textContent = product.Product_Name;
        row.querySelector('.sku-cell').textContent = product.SKU || '';
        row.querySelector('.cat-pill').lastChild.textContent = product.Category;
        row.querySelector('.price-cell').textContent = '$' + price.toFixed(2);

        const stockPill = row.querySelector('.stock-pill');
        stockPill.textContent = qty;
        stockPill.className = 'stock-pill' + (qty <= 0 ? ' out' : qty <= reorderLevel ? ' low' : '');

        const editBtn = row.querySelector('.btn-edit');
        editBtn.dataset.name = product.Product_Name;
        editBtn.dataset.sku = product.SKU || '';
        editBtn.dataset.category = product.Category;
        editBtn.dataset.subcategory = product.SubCategory;
        editBtn.dataset.price = product.Price;
        editBtn.dataset.stock = inventory.QuantityOnHand;
        editBtn.dataset.reorderLevel = inventory.ReorderLevel;
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!activeInventoryId) return;

        errorBox.classList.remove('show');
        errorBox.textContent = '';

        const payload = {
            Product_Name: document.getElementById('editFldName').value.trim(),
            SKU: document.getElementById('editFldSku').value.trim() || undefined,
            Category: document.getElementById('editFldCategory').value,
            SubCategory: document.getElementById('editFldSubCategory').value.trim(),
            Price: document.getElementById('editFldPrice').value,
            QuantityOnHand: document.getElementById('editFldStock').value,
            ReorderLevel: document.getElementById('editFldReorderLevel').value || undefined,
        };

        submitBtn.disabled = true;
        submitBtn.textContent = 'SAVING...';

        try {
            const res = await fetch(URL_TEMPLATE.replace('__ID__', activeInventoryId), {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify(payload),
            });

            if (!res.ok) {
                const data = await res.json().catch(() => ({}));
                const message = data.message
                    || (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                    || 'Could not save changes. Please check the fields and try again.';
                throw new Error(message);
            }

            const inventory = await res.json();
            updateRowInPlace(inventory);
            closeModal();
        } catch (err) {
            const msg = err && err.message ? err.message : 'Could not save changes. Please check the fields and try again.';
            errorBox.textContent = msg;
            errorBox.classList.add('show');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'SAVE CHANGES';
        }
    });
})();