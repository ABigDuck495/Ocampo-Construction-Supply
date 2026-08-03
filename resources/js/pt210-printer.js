// ESC/POS command builder + Web Bluetooth connector for Officom PT-210
class PT210Printer {
    constructor() {
        this.device = null;
        this.characteristic = null;
        // Standard Bluetooth SPP-over-BLE service/characteristic UUIDs used by most
        // generic 58mm thermal printers (including PT-210). If it fails to connect,
        // we fall back to scanning all services.
        this.SERVICE_UUID = '000018f0-0000-1000-8000-00805f9b34fb';
        this.CHAR_UUID = '00002af1-0000-1000-8000-00805f9b34fb';
    }

    async connect() {
        this.device = await navigator.bluetooth.requestDevice({
            filters: [{ namePrefix: 'PT' }, { namePrefix: 'Officom' }],
            optionalServices: [this.SERVICE_UUID]
        });

        const server = await this.device.gatt.connect();
        const service = await server.getPrimaryService(this.SERVICE_UUID);
        this.characteristic = await service.getCharacteristic(this.CHAR_UUID);
        return true;
    }

    async disconnect() {
        if (this.device?.gatt?.connected) {
            this.device.gatt.disconnect();
        }
    }

    async write(bytes) {
        // BLE has a ~20-byte write limit per chunk on many printers, so we chunk it
        const chunkSize = 20;
        for (let i = 0; i < bytes.length; i += chunkSize) {
            const chunk = bytes.slice(i, i + chunkSize);
            await this.characteristic.writeValueWithoutResponse(new Uint8Array(chunk));
            await new Promise(r => setTimeout(r, 20)); // small delay avoids dropped bytes
        }
    }

    // --- ESC/POS command helpers ---
    static textToBytes(str) {
        return Array.from(new TextEncoder().encode(str));
    }

    async printText(text, { bold = false, center = false, doubleSize = false } = {}) {
        let bytes = [];
        bytes.push(0x1B, 0x61, center ? 0x01 : 0x00); // align
        bytes.push(0x1B, 0x45, bold ? 0x01 : 0x00);   // bold
        bytes.push(0x1D, 0x21, doubleSize ? 0x11 : 0x00); // size
        bytes = bytes.concat(PT210Printer.textToBytes(text + '\n'));
        await this.write(bytes);
    }

    async feedAndCut() {
        const bytes = [0x1B, 0x64, 0x03, 0x1D, 0x56, 0x00]; // feed 3 lines + cut
        await this.write(bytes);
    }

    async printReceipt(order) {
        await this.printText('Ocampo Construction Supply', { bold: true, center: true, doubleSize: true });
        await this.printText('--------------------------------', { center: true });
        await this.printText(`Order #: ${order.id}`);
        await this.printText(`Date: ${order.date}`);
        await this.printText('--------------------------------');

        for (const item of order.items) {
            const line = `${item.name.padEnd(18)} x${item.qty}`.padEnd(26) + item.subtotal.toFixed(2);
            await this.printText(line);
        }

        await this.printText('--------------------------------');
        await this.printText(`TOTAL: PHP ${order.total.toFixed(2)}`, { bold: true, doubleSize: true });
        await this.printText('--------------------------------', { center: true });
        await this.printText('Thank you for your purchase!', { center: true });
        await this.feedAndCut();
    }
}

window.PT210Printer = new PT210Printer();