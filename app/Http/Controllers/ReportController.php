<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDailyReportJob;
use App\Models\Delivery;
use App\Models\Dispatch;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Report;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class ReportController extends Controller
{
    /** Orders with this Status are excluded from revenue, order counts, top products and recent sales. */
    private const CANCELLED = 'Cancelled';

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
       REPORTS PAGE — LIVE FROM THE DATABASE
       Every figure below is queried from orders / order_items /
       transactions / dispatches / deliveries on each request. The
       `reports` snapshot table is no longer used by the page.

       Rules (change here if the business rules differ):
       - Period       : Orders.OrderDate within the selected month.
       - Valid order  : Orders.Status is NULL or not 'Cancelled'.
       - Order total  : sum of its Transaction.Amount; if it has no
                        transaction yet, sum of its items
                        (Quantity * UnitPrice, falling back to the
                        product's Price) — same as Order::totalAmount().
       - Items sold   : sum of OrderItem.Quantity.
       ============================================================ */

    private function monthRange(Request $request): array
    {
        $month = (int) ($request->month ?? now()->month);
        $year  = (int) ($request->year ?? now()->year);

        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        return [$start, $end];
    }

    /** Orders of the month that count toward sales (cancelled excluded). */
    private function validOrders(Carbon $start, Carbon $end)
    {
        return Order::query()
            ->whereBetween('orders.OrderDate', [$start, $end])
            ->where(function ($q) {
                $q->whereNull('orders.Status')
                  ->orWhere('orders.Status', '!=', self::CANCELLED);
            });
    }

    /** SQL expression for one order's total (see rules above). Refers to the outer `orders` table. */
    private function orderTotalSql(): string
    {
        $tx = (new Transaction)->getTable();

        return "COALESCE(
            (SELECT SUM(t.Amount) FROM {$tx} t WHERE t.OrderID = orders.OrderID),
            (SELECT SUM(CAST(oi.Quantity AS DECIMAL(12,2)) * COALESCE(oi.UnitPrice, p.Price, 0))
               FROM order_items oi
               LEFT JOIN products p ON p.ProductID = oi.ProductID
              WHERE oi.OrderID = orders.OrderID),
            0)";
    }

    /** SQL expression for one order's total item quantity. */
    private function orderItemsQtySql(): string
    {
        return "(SELECT COALESCE(SUM(CAST(oi2.Quantity AS DECIMAL(12,2))), 0)
                   FROM order_items oi2
                  WHERE oi2.OrderID = orders.OrderID)";
    }

    /** [revenue, orders, average order value] for the month — used by both the header bar and the sales cards. */
    private function monthTotals(Carbon $start, Carbon $end): array
    {
        $row = $this->validOrders($start, $end)
            ->selectRaw('COUNT(*) AS total_orders, COALESCE(SUM(' . $this->orderTotalSql() . '), 0) AS revenue')
            ->first();

        $orders  = (int) ($row->total_orders ?? 0);
        $revenue = round((float) ($row->revenue ?? 0), 2);
        $avg     = $orders ? round($revenue / $orders, 2) : 0.0;

        return [$revenue, $orders, $avg];
    }

    /** Units + revenue per product for valid orders in the month, best sellers first. */
    private function productSales(Carbon $start, Carbon $end)
    {
        return DB::table('order_items as oi')
            ->join('orders', 'orders.OrderID', '=', 'oi.OrderID')
            ->leftJoin('products as p', 'p.ProductID', '=', 'oi.ProductID')
            ->whereBetween('orders.OrderDate', [$start, $end])
            ->where(function ($q) {
                $q->whereNull('orders.Status')
                  ->orWhere('orders.Status', '!=', self::CANCELLED);
            })
            ->groupBy('oi.ProductID', 'p.Product_Name', 'p.Category')
            ->selectRaw('
                oi.ProductID AS product_id,
                p.Product_Name AS name,
                p.Category AS category,
                SUM(CAST(oi.Quantity AS DECIMAL(12,2))) AS units,
                SUM(CAST(oi.Quantity AS DECIMAL(12,2)) * COALESCE(oi.UnitPrice, p.Price, 0)) AS revenue
            ')
            ->orderByDesc('units')
            ->get();
    }

    private function num($value)
    {
        $f = (float) $value;
        return floor($f) == $f ? (int) $f : round($f, 2);
    }

    private function fmtDateTime($value): string
    {
        if (!$value) {
            return '—';
        }
        $c = Carbon::parse($value);
        return $c->format('H:i:s') === '00:00:00' ? $c->format('M d, Y') : $c->format('M d, Y g:i A');
    }

    public function data(Request $request)
    {
        [$start, $end] = $this->monthRange($request);

        [$totalRevenue, $totalOrders, $avgOrderValue] = $this->monthTotals($start, $end);

        // ---- Top products / items ordered / top category (all from order_items) ----
        $sales = $this->productSales($start, $end);

        $maxUnits = (float) ($sales->max('units') ?: 1);

        $topProducts = $sales->take(5)->map(fn($r) => [
            'name' => $r->name ?? 'Unknown Product',
            'unitsSold' => $this->num($r->units),
            'percent' => round(((float) $r->units / $maxUnits) * 100),
        ])->values();

        $itemsOrdered = $sales->map(fn($r) => [
            'productId' => $r->product_id,
            'name' => $r->name ?? 'Unknown Product',
            'category' => $r->category ?? '—',
            'unitsSold' => $this->num($r->units),
            'revenue' => round((float) $r->revenue, 2),
        ])->values();

        $topCategory = $sales
            ->groupBy(fn($r) => $r->category ?: 'Uncategorized')
            ->map(fn($rows) => $rows->sum('units'))
            ->sortDesc()
            ->keys()
            ->first() ?? '—';

        // ---- Recent sales: latest valid orders of the month ----
        $recentSales = $this->validOrders($start, $end)
            ->select('orders.*')
            ->selectRaw($this->orderTotalSql() . ' AS order_total')
            ->selectRaw($this->orderItemsQtySql() . ' AS items_qty')
            ->with('transactions')
            ->orderByDesc('orders.OrderDate')
            ->orderByDesc('orders.OrderID')
            ->limit(20)
            ->get()
            ->map(fn($o) => [
                'id' => $o->OrderID,
                'customer' => $o->CustomerName,
                'items' => $this->num($o->items_qty),
                'total' => round((float) $o->order_total, 2),
                'payment' => $o->transactions->PaymentMethod ?? '—',
                'paymentStatus' => $o->PaymentStatus ?? '—',
                'date' => Carbon::parse($o->OrderDate)->format('M d, Y'),
            ])->values();

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
            'deliveryHistory' => $this->deliveryHistory($start, $end),
            'reportSummaries' => $this->dailySummaries($start, $end),
            'range' => [
                'month' => $start->format('F Y'),
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
        ]);
    }

    /**
     * Delivery History = the month's dispatches (Pending → On Route → Delivered / Failed)
     * with their delivery record, order, truck and main driver.
     */
    private function deliveryHistory(Carbon $start, Carbon $end): array
    {
        return Dispatch::with(['orderItem.order.transactions', 'truck', 'mainDriver', 'delivery'])
            ->whereBetween('DispatchDate', [$start, $end])
            ->orderByDesc('DispatchDate')
            ->orderByDesc('DispatchID')
            ->limit(100)
            ->get()
            ->map(function ($d) {
                $order = optional($d->orderItem)->order;
                $delivery = $d->delivery;

                // NOTE: swap in your real Truck column(s) here if these guesses are wrong.
                $truck = $d->truck
                    ? ($d->truck->getAttribute('PlateNumber') ?? $d->truck->getAttribute('Name') ?? ('Truck #' . $d->TruckID))
                    : '—';

                return [
                    'id' => $order->OrderID ?? '—',
                    'customer' => $order->CustomerName ?? '—',
                    'truck' => $truck,
                    'driver' => optional($d->mainDriver->first())->Name ?? 'Unassigned',
                    'status' => $d->Status ?? '—',
                    'dispatched' => $this->fmtDateTime($d->DispatchDate),
                    'delivered' => ($delivery && $delivery->Status === 'Delivered')
                        ? $this->fmtDateTime($delivery->DeliveryDate)
                        : '—',
                    'payment' => optional(optional($order)->transactions)->PaymentMethod,
                ];
            })->values()->all();
    }

    /**
     * Expandable "daily summary" cards, built live from orders / dispatches / deliveries
     * (one card per day that had any activity, newest first).
     */
    private function dailySummaries(Carbon $start, Carbon $end): array
    {
        $ordersByDay = $this->validOrders($start, $end)
            ->selectRaw('DATE(orders.OrderDate) AS day')
            ->selectRaw('COUNT(*) AS orders_count')
            ->selectRaw('COALESCE(SUM(' . $this->orderTotalSql() . '), 0) AS sales')
            ->selectRaw('COALESCE(SUM(' . $this->orderItemsQtySql() . '), 0) AS items')
            ->groupBy(DB::raw('DATE(orders.OrderDate)'))
            ->get()
            ->keyBy('day');

        $dispatchesByDay = Dispatch::whereBetween('DispatchDate', [$start, $end])
            ->orderBy('DispatchID')
            ->get(['DispatchID', 'Status', 'DispatchDate'])
            ->groupBy(fn($d) => Carbon::parse($d->DispatchDate)->toDateString());

        $deliveriesByDay = Delivery::whereBetween('DeliveryDate', [$start, $end])
            ->orderBy('DeliveryID')
            ->get(['DeliveryID', 'Status', 'DeliveryDate'])
            ->groupBy(fn($d) => Carbon::parse($d->DeliveryDate)->toDateString());

        // Past days keep the low-stock figure that was snapshotted then; today is live.
        $snapshotLowStock = Report::whereBetween('ReportDate', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->mapWithKeys(fn($r) => [$r->ReportDate->toDateString() => (int) ($r->LowStockItemCount ?? 0)]);
        $liveLowStock = Inventory::lowStock()->count();
        $today = now()->toDateString();

        $days = collect($ordersByDay->keys())
            ->merge($dispatchesByDay->keys())
            ->merge($deliveriesByDay->keys())
            ->unique()
            ->sortDesc()
            ->values();

        return $days->map(function ($day) use ($ordersByDay, $dispatchesByDay, $deliveriesByDay, $snapshotLowStock, $liveLowStock, $today) {
            $o = $ordersByDay->get($day);
            $dispatches = $dispatchesByDay->get($day, collect());
            $deliveries = $deliveriesByDay->get($day, collect());

            return [
                'date' => Carbon::parse($day)->format('F j, Y'),
                'generatedAt' => 'live',
                'totalOrders' => (int) ($o->orders_count ?? 0),
                'totalSales' => round((float) ($o->sales ?? 0), 2),
                'totalItemsSold' => $this->num($o->items ?? 0),
                'totalDeliveries' => $deliveries->where('Status', 'Delivered')->count(),
                'totalDispatches' => $dispatches->count(),
                'lowStockItemCount' => $day === $today ? $liveLowStock : (int) ($snapshotLowStock[$day] ?? 0),
                'dispatches' => $dispatches->map(fn($d) => [
                    'DispatchID' => $d->DispatchID,
                    'Status' => $d->Status,
                    'DispatchDate' => $d->DispatchDate,
                ])->values()->all(),
                'deliveries' => $deliveries->map(fn($d) => [
                    'DeliveryID' => $d->DeliveryID,
                    'Status' => $d->Status,
                    'DeliveryDate' => $d->DeliveryDate,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /* ============================================================
       EXPORTS — same period + same valid-order rule as the screen
       ============================================================ */

    /** Valid orders of the selected month, eager-loaded for the PDF/CSV. */
    private function filteredOrders(Request $request)
    {
        [$start, $end] = $this->monthRange($request);

        return $this->validOrders($start, $end)
            ->with(['transactions', 'orderItems.product'])
            ->orderBy('orders.OrderDate')
            ->orderBy('orders.OrderID');
    }

    private function itemsOrderedForRange(Request $request)
    {
        [$start, $end] = $this->monthRange($request);

        return $this->productSales($start, $end)->map(fn($r) => [
            'name' => $r->name ?? 'Unknown Product',
            'category' => $r->category ?? '—',
            'unitsSold' => $this->num($r->units),
            'revenue' => round((float) $r->revenue, 2),
        ])->values();
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
            'totalRevenue' => $orders->sum(fn($o) => $o->totalAmount()),
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
                    $o->totalAmount(),
                    Carbon::parse($o->OrderDate)->format('Y-m-d'),
                ]);
            }
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Header stat bar (top orange strip) for the month/year chosen in its picker
     * (defaults to the current month). Computed live from orders — the same
     * numbers as the Sales Summary cards for that month.
     */
    public function summary(Request $request)
    {
        [$start, $end] = $this->monthRange($request);

        [$totalRevenue, $totalOrders, $avgOrder] = $this->monthTotals($start, $end);

        return response()->json([
            'totalRevenue'  => $totalRevenue,
            'totalOrders'   => $totalOrders,
            'avgOrderValue' => $avgOrder,
            'month'         => $start->format('F Y'),
        ]);
    }
}