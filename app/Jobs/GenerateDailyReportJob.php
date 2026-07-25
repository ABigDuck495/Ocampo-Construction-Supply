<?php
namespace App\Jobs;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\Delivery;
use App\Models\Inventory;
use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateDailyReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $date;

    public function __construct($date = null)
    {
        // Allows manual re-runs for a specific past date, defaults to today
        $this->date = $date ?? now()->toDateString();
    }

    public function handle()
    {
        $date = $this->date;

        // Avoid duplicate reports if the job or manual trigger runs twice for the same day
        $existing = Report::whereDate('ReportDate', $date)->first();
        if ($existing) {
            $existing->delete();
        }

        $totalOrders = Order::whereDate('Order_Date', $date)->count();

        $totalSales = Transaction::whereDate('TransactionDate', $date)->sum('TotalAmount');

        $totalItemsSold = Order::whereDate('Order_Date', $date)
            ->with('orderItems')
            ->get()
            ->flatMap->orderItems
            ->sum('Quantity');

        $totalDeliveries = Delivery::whereDate('DeliveryDate', $date)
            ->where('Status', 'Delivered')
            ->count();

        $totalDeliveriesFailed = Delivery::whereDate('DeliveryDate', $date)
            ->whereIn('Status', ['Failed', 'Returned'])
            ->count();

        $lowStockItemCount = Inventory::lowStock()->count();

        return Report::create([
            'ReportDate' => $date,
            'GeneratedAt' => now(),
            'TotalOrders' => $totalOrders,
            'TotalSales' => $totalSales,
            'TotalItemsSold' => $totalItemsSold,
            'TotalDeliveries' => $totalDeliveries,
            'TotalDeliveriesFailed' => $totalDeliveriesFailed,
            'LowStockItemCount' => $lowStockItemCount,
        ]);
    }
}