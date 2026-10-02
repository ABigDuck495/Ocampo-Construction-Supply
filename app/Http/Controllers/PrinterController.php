<?php

namespace App\Http\Controllers;

use App\Models\Printer;
use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\DummyPrintConnector;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;
use Mike42\Escpos\Printer as EscPrinter;

class PrinterController extends Controller
{
    public function index()
    {
        return view('admin.printers.index', ['printers' => Printer::all()]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'connection_type' => 'required|in:network,usb,bluetooth',
            'ip_address' => 'required_if:connection_type,network|nullable|ip',
            'port' => 'required_if:connection_type,network|nullable|integer',
            'usb_printer_name' => 'required_if:connection_type,usb|nullable|string',
            'bluetooth_service_uuid' => 'nullable|string',
            'bluetooth_characteristic_uuid' => 'nullable|string',
            'bluetooth_com_port' => 'nullable|string|max:20',
            'is_default' => 'boolean',
        ]);

        if ($request->boolean('is_default')) {
            Printer::query()->update(['is_default' => false]);
        }

        Printer::create($validated);

        return redirect()->route('printers.index')->with('success', 'Printer added.');
    }

    public function update(Request $request, Printer $printer)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'connection_type' => 'required|in:network,usb,bluetooth',
            'ip_address' => 'required_if:connection_type,network|nullable|ip',
            'port' => 'required_if:connection_type,network|nullable|integer',
            'usb_printer_name' => 'required_if:connection_type,usb|nullable|string',
            'bluetooth_service_uuid' => 'nullable|string',
            'bluetooth_characteristic_uuid' => 'nullable|string',
            'bluetooth_com_port' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ]);

        if ($request->boolean('is_default')) {
            Printer::query()->where('id', '!=', $printer->id)->update(['is_default' => false]);
        }

        $printer->update($validated);

        return redirect()->route('printers.index')->with('success', 'Printer updated.');
    }

    public function destroy(Printer $printer)
    {
        $printer->delete();

        return redirect()->route('printers.index')->with('success', 'Printer removed.');
    }

    /**
     * Build the ESC/POS byte stream for a receipt.
     * Used by both standalone print and auto-print-after-confirm.
     *
     * $cols = characters per line: 32 for 58mm paper, 48 for 80mm paper.
     *
     * Mirrors the on-screen receipt (renderReceipt() in pos.js) line
     * for line: header, store sub, date, customer, contact, type,
     * address (Delivery only), notes (if present), payment (status),
     * items, total, footer.
     */
    public function buildReceiptBytes(array $order, int $cols = 32): string
    {
        $connector = new DummyPrintConnector();
        $printer = new EscPrinter($connector);
        $line = str_repeat('-', $cols);

        // ---- Header ----
        $printer->setJustification(EscPrinter::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text(($order['store_name'] ?? 'Ocampo Construction and Hardware Supplies') . "\n");
        $printer->setEmphasis(false);
        if (!empty($order['store_sub'])) {
            $printer->text($order['store_sub'] . "\n");
        }

        // ---- Meta block (Date / Customer / Contact / Type / Address / Notes / Payment) ----
        $printer->setJustification(EscPrinter::JUSTIFY_LEFT);
        $printer->text("Date: " . ($order['date'] ?? now()->format('M j, Y g:i A')) . "\n");

        if (!empty($order['customer_name'])) {
            $printer->text("Customer: {$order['customer_name']}\n");
        }
        if (!empty($order['contact'])) {
            $printer->text("Contact: {$order['contact']}\n");
        }
        if (!empty($order['order_type'])) {
            $printer->text("Type: {$order['order_type']}\n");
        }
        if (($order['order_type'] ?? null) === 'Delivery' && !empty($order['address'])) {
            $printer->text("Address: {$order['address']}\n");
        }
        if (!empty($order['notes'])) {
            $printer->text("Notes: {$order['notes']}\n");
        }
        if (!empty($order['payment_method'])) {
            $statusSuffix = !empty($order['payment_status']) ? " ({$order['payment_status']})" : '';
            $printer->text("Payment: {$order['payment_method']}{$statusSuffix}\n");
        } elseif (!empty($order['payment_status'])) {
            // Fallback for receipts with no payment_method (e.g. Delivery Ops,
            // which has no cash/GCash/etc. concept - only a Paid/Unpaid status).
            // Keeps payment status visible on the printed receipt even though
            // it's not a POS-style transaction.
            $printer->text("Payment Status: {$order['payment_status']}\n");
        }

        $printer->text($line . "\n");

        // ---- Items ----
        foreach ($order['items'] as $item) {
            // Line 1: item name inline with quantity — always present.
            $printer->text(sprintf("%s x%d\n", $item['name'], $item['qty']));

            // Line 2: unit price / line total — only when pricing data was
            // actually sent. Delivery Ops receipts don't carry prices, so
            // this is skipped there rather than printing a fake $0.00.
            if (isset($item['price'])) {
                $lineTotal = $item['qty'] * $item['price'];
                $printer->text(sprintf(
                    "%-" . ($cols - 12) . "s %11s\n",
                    '@ ' . number_format($item['price'], 2),
                    number_format($lineTotal, 2)
                ));
            }
        }

        $printer->text($line . "\n");

        // ---- Total (optional — only when the caller sent one) ----
        if (isset($order['total']) && $order['total'] !== null) {
            $printer->setJustification(EscPrinter::JUSTIFY_RIGHT);
            $printer->setEmphasis(true);
            $printer->text("TOTAL: " . number_format($order['total'], 2) . "\n");
            $printer->setEmphasis(false);

            $printer->text($line . "\n");
        }

        // ---- Footer ----
        $printer->setJustification(EscPrinter::JUSTIFY_CENTER);
        $printer->text(($order['footer'] ?? 'Thank you for your purchase!') . "\n");

        $printer->feed(9);
        $printer->cut();

        $data = $connector->getData();
        $printer->close();

        return $data;
    }

    /**
     * POST /api/print-receipt
     * Body: { printer_id, store_name, store_sub, date, customer_name, contact,
     *         order_type, address, notes, payment_method, payment_status,
     *         items[], total, footer }
     */
    public function printReceipt(Request $request)
    {
        $request->validate([
            'printer_id' => 'nullable|exists:printers,id',
            'items' => 'required|array|min:1',
            'total' => 'nullable|numeric',
        ]);

        // Specific printer -> default active printer -> any active printer
        $printer = $request->printer_id
            ? Printer::findOrFail($request->printer_id)
            : (Printer::active()->default()->first() ?? Printer::active()->first());

        if (!$printer) {
            return response()->json(['status' => 'error', 'message' => 'No printer configured.'], 422);
        }

        // 58mm Bluetooth printers = 32 cols, 80mm (XP-80) = 48 cols
        $cols = $printer->connection_type === 'bluetooth' ? 32 : 48;
        $rawBytes = $this->buildReceiptBytes($request->all(), $cols);

        // ---- Network (LAN) printer: raw socket to ip:9100 ----
        if ($printer->connection_type === 'network') {
            $socket = @fsockopen($printer->ip_address, (int) $printer->port, $errno, $errstr, 5);

            if (!$socket) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Could not reach printer at {$printer->ip_address}:{$printer->port} - {$errstr} (code {$errno})",
                ], 500);
            }

            $written = fwrite($socket, $rawBytes);
            fclose($socket);

            if ($written === false || $written < strlen($rawBytes)) {
                return response()->json(['status' => 'error', 'message' => 'Printer connection dropped while sending data.'], 500);
            }

            return response()->json(['status' => 'printed']);
        }

        // ---- USB printer: Laravel runs on the same Windows PC, so send the
        // bytes through the Windows shared-printer name (usb_printer_name). ----
        if ($printer->connection_type === 'usb') {
            try {
                $connector = new WindowsPrintConnector($printer->usb_printer_name);
                $connector->write($rawBytes);
                $connector->finalize();
            } catch (\Throwable $e) {
                return response()->json([
                    'status' => 'error',
                    'message' => "USB print failed for '{$printer->usb_printer_name}': " . $e->getMessage()
                        . ' (is the printer shared in Windows with that exact share name?)',
                ], 500);
            }

            return response()->json(['status' => 'printed']);
        }

        // bluetooth: server can't reach it directly,
        // hand the bytes back to the browser to deliver over Web Serial
        return response()->json([
            'status' => 'ready_for_client_print',
            'connection_type' => $printer->connection_type,
            'usb_printer_name' => $printer->usb_printer_name,
            'bluetooth_com_port' => $printer->bluetooth_com_port,
            'raw_base64' => base64_encode($rawBytes),
        ]);
    }
}