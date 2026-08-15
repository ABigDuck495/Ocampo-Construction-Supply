/**
 * printReceipt.js
 * Handles sending a receipt to whichever printer is configured
 * (network / usb / bluetooth). Network prints resolve server-side;
 * usb and bluetooth get relayed from here in the browser.
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

async function printViaQzUsb(usbPrinterName, bytes) {
    if (typeof qz === 'undefined') {
        throw new Error('QZ Tray is not loaded. Install/run QZ Tray to print via USB.');
    }
    if (!qz.websocket.isActive()) {
        await qz.websocket.connect();
    }
    const config = qz.configs.create(usbPrinterName);
    const data = [{ type: 'raw', format: 'base64', data: btoa(String.fromCharCode(...bytes)) }];
    await qz.print(config, data);
}

async function printViaBluetoothSerial(comPort, bytes) {
    // Most Bluetooth thermal printers (incl. POS-58BT clones) use classic
    // SPP, which Windows exposes as a virtual COM port after pairing.
    // QZ Tray's serial API talks to that COM port directly — this is NOT
    // the Web Bluetooth API, which only supports BLE devices.
    if (typeof qz === 'undefined') {
        throw new Error('QZ Tray is not loaded. Install/run QZ Tray to print via Bluetooth.');
    }
    if (!qz.websocket.isActive()) {
        await qz.websocket.connect();
    }

    // Defensively close the port first in case a previous attempt errored
    // out before its own closePort() ran, leaving it locked open.
    try {
        await qz.serial.closePort(comPort);
    } catch (err) {
        // ignore — port likely wasn't open, which is the normal case
    }

    await qz.serial.openPort(comPort, {
        baudRate: 9600, // common default for these printers; adjust if yours differs
    });

    try {
        await qz.serial.sendData(comPort, {
            type: 'base64',
            data: btoa(String.fromCharCode(...bytes)),
        });
    } finally {
        await qz.serial.closePort(comPort);
    }
}

/**
 * @param {Object} order - { printer_id?, items: [{name, qty, price}], total, order_type, customer_name, payment_method }
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
            if (result.connection_type === 'usb') {
                await printViaQzUsb(result.usb_printer_name, bytes);
            } else if (result.connection_type === 'bluetooth') {
                await printViaBluetoothSerial(result.bluetooth_com_port, bytes);
            }
            return { status: 'printed' };
        } catch (err) {
            return { status: 'error', message: err.message };
        }
    }

    return { status: 'error', message: 'Unexpected response from print endpoint.' };
}