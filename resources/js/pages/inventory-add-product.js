// resources/js/pages/inventory-add-product.js
//
// Wires up the "+ ADD PRODUCT" button and modal on the inventory page.
// Kept in its own file (rather than merged into inventory.js) so it
// doesn't risk clobbering whatever search/filter/render logic already
// lives there.
//
// Posts to InventoryController@storeWithProduct (route: inventory.storeWithProduct),
// which creates a Product row AND its Inventory row together in one
// DB transaction. The URL is read from the form's data-store-url
// attribute (set via route() in the blade) rather than hardcoded here.
//
// Response shape (Inventory model with product loaded):
// {
//   InventoryID, ProductID, QuantityOnHand, ReorderLevel, ...,
//   product: { ProductID, Product_Name, SKU, Category, SubCategory, Price, ... }
// }

(function () {
    const modal = document.getElementById('addProductModal');
    const openBtn = document.getElementById('addProductBtn');
    const closeBtn = document.getElementById('modalCloseBtn');
    const cancelBtn = document.getElementById('modalCancelBtn');
    const form = document.getElementById('addProductForm');
    const submitBtn = document.getElementById('modalSubmitBtn');
    const errorBox = document.getElementById('modalFormError');
    const productBody = document.getElementById('productBody');

    if (!modal || !openBtn || !form) return; // page markup not present, bail quietly

    const STORE_URL = form.dataset.storeUrl;

    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function openModal() {
        modal.classList.add('open');
        errorBox.classList.remove('show');
        errorBox.textContent = '';
        form.reset();
        document.getElementById('fldName').focus();
    }

    function closeModal() {
        modal.classList.remove('open');
    }

    openBtn.addEventListener('click', openModal);
    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    modal.addEventListener('click', (e) => {
        if (e.target === modal) closeModal();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('open')) closeModal();
    });

    function categoryIconSvg() {
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>';
    }

    function stockPillClass(qty, reorderLevel) {
        if (qty <= 0) return 'stock-pill out';
        if (qty <= reorderLevel) return 'stock-pill low';
        return 'stock-pill';
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function appendProductRow(inventory) {
        const emptyRow = productBody.querySelector('.empty-row');
        if (emptyRow) emptyRow.remove();

        const product = inventory.product;
        const qty = Number(inventory.QuantityOnHand);
        const reorderLevel = Number(inventory.ReorderLevel);
        const price = Number(product.Price);

        const tr = document.createElement('tr');
        tr.dataset.inventoryId = inventory.InventoryID ?? '';
        tr.dataset.productId = product.ProductID ?? '';
        tr.innerHTML = `
            <td class="col-product">
                <div class="prod-cell">${categoryIconSvg()}<span>${escapeHtml(product.Product_Name)}</span></div>
            </td>
            <td class="sku-cell">${escapeHtml(product.SKU)}</td>
            <td><span class="cat-pill">${escapeHtml(product.Category)}</span></td>
            <td class="price-cell">$${price.toFixed(2)}</td>
            <td><span class="${stockPillClass(qty, reorderLevel)}">${qty}</span></td>
            <td class="actions-cell">
                <button type="button" class="btn-edit">EDIT</button>
                <button type="button" class="btn-del">DELETE</button>
            </td>
        `;
        productBody.appendChild(tr);
    }

    function updateHeaderStats(inventory) {
        const statTotal = document.getElementById('statTotal');
        const statValue = document.getElementById('statValue');
        const statOut = document.getElementById('statOut');
        const statLow = document.getElementById('statLow');

        const qty = Number(inventory.QuantityOnHand);
        const reorderLevel = Number(inventory.ReorderLevel);
        const price = Number(inventory.product.Price);

        if (statTotal) statTotal.textContent = String(Number(statTotal.textContent || '0') + 1);

        if (statValue) {
            const current = parseFloat(statValue.textContent.replace(/[^0-9.]/g, '')) || 0;
            statValue.textContent = '$' + (current + price * qty).toFixed(2);
        }

        if (qty <= 0 && statOut) {
            statOut.textContent = String(Number(statOut.textContent || '0') + 1);
        } else if (qty > 0 && qty <= reorderLevel && statLow) {
            statLow.textContent = String(Number(statLow.textContent || '0') + 1);
        }
    }

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorBox.classList.remove('show');
        errorBox.textContent = '';

        const payload = {
            Product_Name: document.getElementById('fldName').value.trim(),
            SKU: document.getElementById('fldSku').value.trim() || undefined,
            Category: document.getElementById('fldCategory').value,
            SubCategory: document.getElementById('fldSubCategory').value.trim(),
            Price: document.getElementById('fldPrice').value,
            QuantityOnHand: document.getElementById('fldStock').value,
            ReorderLevel: document.getElementById('fldReorderLevel').value || undefined,
        };

        submitBtn.disabled = true;
        submitBtn.textContent = 'SAVING...';

        try {
            const res = await fetch(STORE_URL, {
                method: 'POST',
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
                    || 'Could not save product. Please check the fields and try again.';
                throw new Error(message);
            }

            const inventory = await res.json();

            // Add the new row to the table and update header stats
            appendProductRow(inventory);
            updateHeaderStats(inventory);
            closeModal();
        } catch (err) {
            const msg = err && err.message ? err.message : 'Could not save product. Please check the fields and try again.';
            errorBox.textContent = msg;
            errorBox.classList.add('show');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = 'SAVE PRODUCT';
        }
    });
})();