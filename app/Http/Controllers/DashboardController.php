<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\Truck;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = Carbon::today('Asia/Manila');
        $todayRevenue = $this->revenueForDate($today);
        $yesterdayRevenue = $this->revenueForDate($today->copy()->subDay());

        $revenueDelta = $yesterdayRevenue > 0
            ? round((($todayRevenue - $yesterdayRevenue) / $yesterdayRevenue) * 100, 1)
            : null;

        $pendingOrders = Order::query()
            ->where('Status', 'Pending')
            ->with(['transactions', 'orderItems.product'])
            ->latest('OrderDate')
            ->take(6)
            ->get();

        $lowStockItems = Inventory::query()
            ->lowStock()
            ->with('product')
            ->orderBy('QuantityOnHand')
            ->take(6)
            ->get();

        return view('homepage.index', [
            'todayRevenue' => (float) $todayRevenue,
            'revenueDelta' => $revenueDelta,
            'ordersToday' => Order::query()->whereDate('OrderDate', today())->count(),
            'pendingCount' => Order::query()->where('Status', 'Pending')->count(),
            'trucksEnRoute' => Truck::query()->where('Status', 'On Route')->count(),
            'totalTrucks' => Truck::query()->count(),
            'lowStockCount' => Inventory::lowStock()->count(),
            'pendingOrders' => $pendingOrders,
            'lowStockItems' => $lowStockItems,
        ]);
    }

    public function revenue(): JsonResponse
    {
        return response()->json([
            'todayRevenue' => $this->revenueForDate(Carbon::today('Asia/Manila')),
        ]);
    }

    private function revenueForDate($date): float
    {
        $start = $date->copy()->startOfDay()->setTimezone('UTC');
        $end = $date->copy()->endOfDay()->setTimezone('UTC');

        $transactionRevenue = Transaction::query()
            ->whereBetween('TransactionDate', [$start, $end])
            ->whereHas('order', fn ($query) => $query
                ->where('PaymentStatus', Order::PAYMENT_STATUS_PAID))
            ->sum('Amount');

        $paidOrdersWithoutTransactions = Order::query()
            ->where('PaymentStatus', Order::PAYMENT_STATUS_PAID)
            ->whereBetween('PaidAt', [$start, $end])
            ->whereDoesntHave('transactions')
            ->with('orderItems.product')
            ->get();

        $fallbackRevenue = $paidOrdersWithoutTransactions->sum(
            fn (Order $order) => $order->orderItems->sum(fn ($item) => $item->subtotal())
        );

        return (float) $transactionRevenue + $fallbackRevenue;
    }
}