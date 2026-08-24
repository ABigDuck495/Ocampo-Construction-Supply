/**
 * printReceipt.js
 * Sends a receipt to whichever printer is configured. Network prints
 * resolve entirely server-side (PrinterController writes raw bytes
 * straight to the printer's IP/port). USB/Bluetooth thermal prints
 * are written through window.pt210 (see pt210-printer.js) — that
 * module owns the one shared Web Serial connection for the whole
 * app, including the header's Connect/status indicator, so printing
 * here never opens its own separate port.
 *
 * Usage from pos.js:
 *   import { printReceipt } from './printReceipt.js';
 *   await printReceipt(orderPayload);
 */

function base64ToBytes(base64) {
    const binary = atob(base64);
    const bytes = new Uint8Array(binary.length);
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }
    return bytes;
}

/**
 * Writes bytes to the printer via the shared pt210 connection.
 * Does NOT prompt for a new device — requestPort() needs a direct,
 * fresh user gesture, and by the time this runs we're already a few
 * awaits deep inside a click handler (past the /api/print-receipt
 * fetch), so that gesture may no longer count. Instead:
 *   1. If already connected, just write.
 *   2. If not, try a silent reconnect (uses a previously-granted
 *      port, no prompt) — covers "page was refreshed" etc.
 *   3. If still not connected, fail with a message pointing the
 *      cashier at the header's "Connect Printer" button, which is
 *      the one place a fresh picker prompt is safe to trigger.
 */
async function writeViaPt210(bytes) {
    if (!window.pt210) {
        throw new Error('Printer module did not load. Refresh the page and try again.');
    }
    if (!window.pt210.isSupported()) {
        throw new Error('This browser can\'t talk to the printer directly. Use Chrome or Edge on desktop.');
    }

    if (!window.pt210.isConnected()) {
        const reconnected = await window.pt210.tryReconnect();
        if (!reconnected) {
            throw new Error('Printer not connected. Click "Connect Printer" at the top of the page first.');
        }
    }

    await window.pt210.write(bytes);
}

/**
 * @param {Object} order - { printer_id?, store_name?, store_sub?, date?, items: [{name, qty, price}], total, order_type, customer_name, contact?, address?, notes?, payment_method, payment_status?, footer? }
 * @returns {Promise<{ status: string, message?: string }>}
 */
export async function printReceipt(order) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    const response = await fetch('/api/print-receipt', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken,
        },
        body: JSON.stringify(order),
    });

    const result = await response.json();

    if (!response.ok) {
        return { status: 'error', message: result.message || 'Print failed.' };
    }

    if (result.status === 'printed') {
        return { status: 'printed' };
    }

    if (result.status === 'ready_for_client_print') {
        const bytes = base64ToBytes(result.raw_base64);

        try {
            await writeViaPt210(bytes);
            return { status: 'printed' };
        } catch (err) {
            return { status: 'error', message: err.message };
        }
    }

    return { status: 'error', message: 'Unexpected response from print endpoint.' };
}