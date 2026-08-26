<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #222; }
    h1 { font-size: 18px; margin-bottom: 0; }
    .sub { color: #666; margin-top: 4px; margin-bottom: 16px; }
    table { width: 100%; border-collapse: collapse; }
    th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
    th { background: #f5f5f5; }
    .totals { margin: 12px 0; font-weight: bold; }
</style>
</head>
<body>
    <h1>Ocampo Construction and Hardware Supplies</h1>
    <div class="sub">{{ $reportType === 'items' ? 'Items Ordered Report' : 'Sales Report' }}: {{ $monthLabel }}</div>

    @if($reportType === 'items')
        <div class="totals">
            Total Items Sold: {{ $totalItems }} &nbsp;|&nbsp;
            Total Revenue: &#8369;{{ number_format($totalRevenue, 2) }}
        </div>

        <table>
            <thead>
                <tr><th>Product</th><th>Category</th><th>Units Sold</th><th>Revenue</th></tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['category'] }}</td>
                    <td>{{ $item['unitsSold'] }}</td>
                    <td>&#8369;{{ number_format($item['revenue'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="4">No items in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    @else
        <div class="totals">
            Total Revenue: &#8369;{{ number_format($totalRevenue, 2) }} &nbsp;|&nbsp;
            Total Orders: {{ $totalOrders }}
        </div>

        <table>
            <thead>
                <tr><th>Order</th><th>Customer</th><th>Payment</th><th>Status</th><th>Total</th><th>Date</th></tr>
            </thead>
            <tbody>
                @forelse($orders as $o)
                <tr>
                    <td>{{ $o->OrderID }}</td>
                    <td>{{ $o->CustomerName }}</td>
                    <td>{{ $o->transactions->PaymentMethod ?? '—' }}</td>
                    <td>{{ $o->PaymentStatus }}</td>
                    <td>&#8369;{{ number_format($o->transactions->Amount ?? 0, 2) }}</td>
                    <td>{{ \Carbon\Carbon::parse($o->OrderDate)->format('M d, Y') }}</td>
                </tr>
                @empty
                <tr><td colspan="6">No orders in this range.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif
</body>
</html>