<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDailyReportJob;
use App\Models\Report;
use App\Models\Order;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportController extends Controller
{
    public function index()
    {
        $reports = Report::with([
            'dispatches.orderItem.order',
            'dispatches.truck',
            'deliveries.dispatch.orderItem.order',
        ])->latest('ReportDate')->paginate(20);

        return view('reports.index', [
            'reports' => $reports,
        ]);
    }

    public function create()
    {
        return response()->json(['message' => 'Create report endpoint.'], 200);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'ReportDate' => 'required|date',
            'GeneratedAt' => 'nullable|date',
            'TotalOrders' => 'nullable|integer',
            'TotalSales' => 'nullable|numeric',
            'TotalItemsSold' => 'nullable|integer',
            'TotalDeliveries' => 'nullable|integer',
            'TotalDispatches' => 'nullable|integer',
            'Notes' => 'nullable|string',
        ]);

        return Report::create($validated);
    }

    public function show(string $id)
    {
        return Report::findOrFail($id);
    }

    public function edit(string $id)
    {
        return Report::findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'ReportDate' => 'sometimes|required|date',
            'GeneratedAt' => 'nullable|date',
            'TotalOrders' => 'nullable|integer',
            'TotalSales' => 'nullable|numeric',
            'TotalItemsSold' => 'nullable|integer',
            'TotalDeliveries' => 'nullable|integer',
            'TotalDispatches' => 'nullable|integer',
            'Notes' => 'nullable|string',
        ]);

        $report = Report::findOrFail($id);
        $report->fill($validated);
        $report->save();

        return $report;
    }

    public function destroy(string $id)
    {
        $report = Report::findOrFail($id);
        $report->delete();

        return response()->json(['message' => 'Report deleted successfully.'], 200);
    }

    public function generateNow()
    {
        return app(GenerateDailyReportJob::class)->handle();
    }

    public function forDate(Request $request)
    {
        return Report::forDate($request->date ?? today())->firstOrFail();
    }

    public function trend(Request $request)
    {
        return Report::query()
            ->whereBetween('ReportDate', [$request->start, $request->end])
            ->orderBy('ReportDate')
            ->get(['ReportDate', 'TotalSales', 'TotalOrders', 'TotalDeliveries']);
    }

    /* ============================================================
       REPORTS PAGE: MONTH/YEAR FILTER, DATA FEED, EXPORTS
       Revenue lives on Transaction.Amount (Order hasOne Transaction).
       Items sold lives on OrderItem.Quantity (Order hasMany OrderItem).
       ============================================================ */

    private function monthRange(Request $request): array
    {
        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year ?? now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        return [$start, $end];
    }

    /**
     * Orders in the selected month, with transaction + items + product eager-loaded.
     */
    private function filteredOrders(Request $request)
    {
        [$start, $end] = $this->monthRange($request);

        return Order::with(['transactions', 'orderItems.product'])
            ->whereBetween('OrderDate', [$start, $end]);
    }

    public function data(Request $request)
    {
        [$start, $end] = $this->monthRange($request);

        $orders = $this->filteredOrders($request)->get();

        $totalRevenue  = $orders->sum(fn($o) => $o->transactions->Amount ?? 0);
        $totalOrders   = $orders->count();
        $avgOrderValue = $totalOrders ? $totalRevenue / $totalOrders : 0;

        // Flatten all order items across the filtered orders to build Top Products
        $allItems = $orders->flatMap->orderItems;

        $topProducts = $allItems
            ->groupBy(fn($item) => optional($item->product)->ProductID)
            ->map(function ($items) {
                $product = optional($items->first())->product;
                return [
                    'name' => $product->Product_Name ?? 'Unknown Product',
                    'unitsSold' => $items->sum('Quantity'),
                ];
            })
            ->sortByDesc('unitsSold')
            ->take(5)
            ->values();

        $maxUnits = $topProducts->max('unitsSold') ?: 1;
        $topProducts = $topProducts->map(fn($p) => [
            'name' => $p['name'],
            'unitsSold' => $p['unitsSold'],
            'percent' => round(($p['unitsSold'] / $maxUnits) * 100),
        ]);

        // Top category, if your products table has a Category column
        $topCategory = $allItems
            ->groupBy(fn($item) => optional($item->product)->Category ?? 'Uncategorized')
            ->map(fn($items) => $items->sum('Quantity'))
            ->sortDesc()
            ->keys()
            ->first() ?? '—';

        $recentSales = $orders->sortByDesc('OrderDate')->take(20)->map(fn($o) => [
            'id' => $o->OrderID,
            'customer' => $o->CustomerName,
            'items' => $o->orderItems->sum('Quantity'),
            'total' => $o->transactions->Amount ?? 0,
            'payment' => $o->transactions->PaymentMethod ?? '—',
            'paymentStatus' => $o->PaymentStatus ?? '—',
            'date' => \Carbon\Carbon::parse($o->OrderDate)->format('M d, Y'),
        ])->values();

        // Itemized breakdown: every product ordered this month (not capped at 5
        // like topProducts), used by the "Items Ordered" report-type view.
        $itemsOrdered = $allItems
            ->groupBy(fn($item) => optional($item->product)->ProductID)
            ->map(function ($group) {
                $product = optional($group->first())->product;
                return [
                    'productId' => $product->ProductID ?? null,
                    'name' => $product->Product_Name ?? 'Unknown Product',
                    'category' => $product->Category ?? '—',
                    'unitsSold' => $group->sum('Quantity'),
                    'revenue' => $group->sum(fn($item) => ($item->Price ?? optional($item->product)->Price ?? 0) * $item->Quantity),
                ];
            })
            ->sortByDesc('unitsSold')
            ->values();

        $reportSummaries = Report::with(['dispatches', 'deliveries'])
            ->whereBetween('ReportDate', [$start->toDateString(), $end->toDateString()])
            ->orderByDesc('ReportDate')
            ->get()
            ->map(function ($report) {
                return [
                    'date' => $report->ReportDate->format('F j, Y'),
                    'generatedAt' => $report->GeneratedAt ? $report->GeneratedAt->format('M j, Y g:i A') : '—',
                    'totalOrders' => (int) ($report->TotalOrders ?? 0),
                    'totalSales' => (float) ($report->TotalSales ?? 0),
                    'totalItemsSold' => (int) ($report->TotalItemsSold ?? 0),
                    'totalDeliveries' => (int) ($report->TotalDeliveries ?? 0),
                    'totalDispatches' => (int) ($report->TotalDispatches ?? 0),
                    'lowStockItemCount' => (int) ($report->LowStockItemCount ?? 0),
                    'dispatches' => $report->dispatches->map(fn($dispatch) => [
                        'DispatchID' => $dispatch->DispatchID,
                        'Status' => $dispatch->Status,
                        'DispatchDate' => $dispatch->DispatchDate,
                    ])->values()->all(),
                    'deliveries' => $report->deliveries->map(fn($delivery) => [
                        'DeliveryID' => $delivery->DeliveryID,
                        'Status' => $delivery->Status,
                        'DeliveryDate' => $delivery->DeliveryDate,
                    ])->values()->all(),
                ];
            })->values()->all();

        return response()->json([
            'salesStats' => [
                'totalRevenue' => $totalRevenue,
                'totalOrders' => $totalOrders,
                'avgOrderValue' => $avgOrderValue,
                'topCategory' => $topCategory,
            ],
            'topProducts' => $topProducts,
            'recentSales' => $recentSales,
            'itemsOrdered' => $itemsOrdered,
            'deliveryHistory' => [],
            'reportSummaries' => $reportSummaries,
            'range' => [
                'month' => $start->format('F Y'),
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ]);
    }

    /**
     * Shared by exportPdf/exportCsv so both respect the report-type
     * selector (?type=orders|items) coming from the filter bar.
     */
    private function itemsOrderedForRange(Request $request)
    {
        $orders = $this->filteredOrders($request)->get();
        $allItems = $orders->flatMap->orderItems;

        return $allItems
            ->groupBy(fn($item) => optional($item->product)->ProductID)
            ->map(function ($group) {
                $product = optional($group->first())->product;
                return [
                    'name' => $product->Product_Name ?? 'Unknown Product',
                    'category' => $product->Category ?? '—',
                    'unitsSold' => $group->sum('Quantity'),
                    'revenue' => $group->sum(fn($item) => ($item->Price ?? optional($item->product)->Price ?? 0) * $item->Quantity),
                ];
            })
            ->sortByDesc('unitsSold')
            ->values();
    }

    public function exportPdf(Request $request)
    {
        [$start, $end] = $this->monthRange($request);
        $type = $request->type === 'items' ? 'items' : 'orders';

        if ($type === 'items') {
            $items = $this->itemsOrderedForRange($request);

            $pdf = Pdf::loadView('reports.pdf', [
                'reportType' => 'items',
                'monthLabel' => $start->format('F Y'),
                'items' => $items,
                'totalItems' => $items->sum('unitsSold'),
                'totalRevenue' => $items->sum('revenue'),
            ])->setPaper('a4', 'portrait');

            return $pdf->download('items-ordered-' . $start->format('Y-m') . '.pdf');
        }

        $orders = $this->filteredOrders($request)->get();

        $pdf = Pdf::loadView('reports.pdf', [
            'reportType' => 'orders',
            'orders' => $orders,
            'monthLabel' => $start->format('F Y'),
            'totalRevenue' => $orders->sum(fn($o) => $o->transactions->Amount ?? 0),
            'totalOrders' => $orders->count(),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('sales-report-' . $start->format('Y-m') . '.pdf');
    }

    public function exportCsv(Request $request)
    {
        [$start, $end] = $this->monthRange($request);
        $type = $request->type === 'items' ? 'items' : 'orders';

        if ($type === 'items') {
            $items = $this->itemsOrderedForRange($request);
            $filename = 'items-ordered-' . $start->format('Y-m') . '.csv';

            return response()->streamDownload(function () use ($items) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['Product', 'Category', 'Units Sold', 'Revenue']);
                foreach ($items as $item) {
                    fputcsv($handle, [$item['name'], $item['category'], $item['unitsSold'], $item['revenue']]);
                }
                fclose($handle);
            }, $filename, ['Content-Type' => 'text/csv']);
        }

        $orders = $this->filteredOrders($request)->get();
        $filename = 'sales-report-' . $start->format('Y-m') . '.csv';

        return response()->streamDownload(function () use ($orders) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Order ID', 'Customer', 'Payment Method', 'Payment Status', 'Amount', 'Date']);
            foreach ($orders as $o) {
                fputcsv($handle, [
                    $o->OrderID,
                    $o->CustomerName,
                    $o->transactions->PaymentMethod ?? '',
                    $o->PaymentStatus,
                    $o->transactions->Amount ?? 0,
                    \Carbon\Carbon::parse($o->OrderDate)->format('Y-m-d'),
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    // ReportController.php

    /**
     * Header stat bar (top orange strip). Scoped to the CURRENT calendar
     * month only — it naturally "resets" on the 1st of each month since
     * it always queries against now()->month / now()->year rather than
     * summing the whole Report table.
     */
     public function summary(Request $request)
    {
        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year ?? now()->year);
 
        $reports = Report::whereMonth('ReportDate', $month)
            ->whereYear('ReportDate', $year)
            ->get();
 
        $totalRevenue = $reports->sum('TotalSales');
        $totalOrders  = $reports->sum('TotalOrders');
        $avgOrder     = $totalOrders ? $totalRevenue / $totalOrders : 0;
 
        return response()->json([
            'totalRevenue'  => $totalRevenue,
            'totalOrders'   => $totalOrders,
            'avgOrderValue' => $avgOrder,
            'month'         => Carbon::create($year, $month, 1)->format('F Y'),
        ]);
    }
}

