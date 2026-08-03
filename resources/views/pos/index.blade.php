@vite('resources/js/pt210-printer.js')

<button id="printReceiptBtn" class="btn btn-brand">
    🖨️ Print Receipt
</button>

<span id="printerStatus" class="text-muted small ms-2">Printer not connected</span>

@php
    $orderData = [
        'id'    => isset($order) ? $order->id : '0001',
        'items' => isset($order) ? $order->items : [
            ['name' => 'Cement 40kg', 'qty' => 5, 'subtotal' => 1250.00],
            ['name' => 'Plywood 1/4"', 'qty' => 10, 'subtotal' => 3500.00],
        ],
        'total' => isset($order) ? $order->total : 4750.00,
    ];
@endphp

<script>
document.getElementById('printReceiptBtn').addEventListener('click', async () => {
    const statusEl = document.getElementById('printerStatus');
    try {
        if (!window.PT210Printer.characteristic) {
            statusEl.textContent = 'Connecting to printer...';
            await window.PT210Printer.connect();
            statusEl.textContent = 'Printer connected ✅';
        }

        const order = {
            id: '{{ $orderData['id'] }}',
            date: new Date().toLocaleString(),
            items: @json($orderData['items']),
            total: {{ $orderData['total'] }}
        };

        await window.PT210Printer.printReceipt(order);
        statusEl.textContent = 'Receipt printed ✅';
    } catch (err) {
        console.error(err);
        statusEl.textContent = 'Print failed: ' + err.message;
    }
});
</script>