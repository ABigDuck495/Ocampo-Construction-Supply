/* ============================================================
   PT210 PRINTER - Web Serial (Bluetooth Classic SPP / USB)
   ============================================================ */

class PT210Printer {
    constructor() {
        this.port = null;
        this.writer = null;
        this.onStatusChange = null; // (status) => {}, status = 'connected' | 'disconnected'
    }

    isSupported() {
        return 'serial' in navigator;
    }

    isConnected() {
        return this.port !== null && this.writer !== null;
    }

    // Called from a user click (e.g. "Connect Printer" button). Shows the OS port picker.
    async connect() {
        this.port = await navigator.serial.requestPort();
        await this._open();
    }

    // Called on page load. Reuses a previously granted port with NO prompt.
    // Returns true if it reconnected, false if nothing was previously granted.
    async tryReconnect() {
        const ports = await navigator.serial.getPorts();
        if (ports.length === 0) return false;

        this.port = ports[0];
        await this._open();
        return true;
    }

    async _open() {
        await this.port.open({ baudRate: 9600 });
        this.writer = this.port.writable.getWriter();

        this.port.addEventListener('disconnect', () => {
            this.writer = null;
            this.port = null;
            if (this.onStatusChange) this.onStatusChange('disconnected');
        });

        if (this.onStatusChange) this.onStatusChange('connected');
    }

    async disconnect() {
        if (this.writer) {
            this.writer.releaseLock();
            this.writer = null;
        }
        if (this.port) {
            await this.port.close();
            this.port = null;
        }
        if (this.onStatusChange) this.onStatusChange('disconnected');
    }

    async write(bytes) {
        if (!this.isConnected()) throw new Error('Printer not connected');
        await this.writer.write(new Uint8Array(bytes));
    }

    static textToBytes(text) {
        return Array.from(new TextEncoder().encode(text));
    }

    async printText(text, { bold = false, center = false, doubleSize = false } = {}) {
        let bytes = [];
        bytes.push(0x1B, 0x61, center ? 0x01 : 0x00);   // align
        bytes.push(0x1B, 0x45, bold ? 0x01 : 0x00);     // bold
        bytes.push(0x1D, 0x21, doubleSize ? 0x11 : 0x00); // size
        bytes = bytes.concat(PT210Printer.textToBytes(text + '\n'));
        await this.write(bytes);
    }

    async feedAndCut() {
        await this.write([0x1B, 0x64, 0x03, 0x1D, 0x56, 0x00]); // feed 3 lines + cut
    }
}

// Singleton instance shared across the app
window.pt210 = new PT210Printer();

export default window.pt210;