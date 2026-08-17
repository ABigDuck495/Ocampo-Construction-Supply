/**
 * printReceipt.js
 * Sends a receipt to whichever printer is configured. Network prints
 * resolve entirely server-side (PrinterController writes raw bytes
 * straight to the printer's IP/port). USB and Bluetooth thermal
 * printers are printed via the Web Serial API directly from the
 * browser — no separate desktop app (QZ Tray, etc.) required.
 *
 * Browser support: Web Serial only exists in Chromium browsers
 * (Chrome, Edge, Brave, Opera) on desktop, and only in a secure
 * context (https:// or http://localhost / http://127.0.0.1).
 * Firefox and Safari don't implement it, and it isn't available on
 * mobile at all.
 *
 * First print ever on a given browser profile pops the browser's own
 * "select a device" picker so the cashier can choose the printer's
 * COM port. The browser remembers that choice after that — every
 * print after the first is silent, no prompt.
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

/* ----------------------------------------------------------
   Web Serial helpers
   ---------------------------------------------------------- */

/**
 * Returns a SerialPort the browser already has permission for, or —
 * only on the very first print — prompts the cashier to pick one.
 * Must be called as a direct result of a click, since requestPort()
 * requires a live user gesture; keep any work before this call to a
 * minimum so the browser doesn't consider the gesture "stale".
 */
async function getSerialPort() {
    if (!('serial' in navigator)) {
        throw new Error('This browser can\'t talk to the printer directly. Use Chrome or Edge on desktop.');
    }

    const granted = await navigator.serial.getPorts();
    if (granted.length > 0) {
        return granted[0];
    }

    return navigator.serial.requestPort();
}

async function printViaWebSerial(bytes, baudRate = 9600) {
    const port = await getSerialPort();

    // Defensively close first in case a previous attempt errored out
    // before its own close() ran, leaving the port locked open.
    if (port.readable || port.writable) {
        try { await port.close(); } catch (err) { /* ignore — likely already closed */ }
    }

    await port.open({ baudRate });

    const writer = port.writable.getWriter();
    try {
        await writer.write(bytes);
    } finally {
        writer.releaseLock();
        await port.close();
    }
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
            // Both usb and bluetooth thermal printers show up to the OS
            // as a serial/COM port, so both print the same way now —
            // connection_type is no longer branched on here.
            await printViaWebSerial(bytes);
            return { status: 'printed' };
        } catch (err) {
            return { status: 'error', message: err.message };
        }
    }

    return { status: 'error', message: 'Unexpected response from print endpoint.' };
}