<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
    public function posSale(Request $request){
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.ProductID' => 'required|exists:products,ProductID',
            'items.*.Quantity' => 'required|integer|min:1',
            'items.*.UnitPrice' => 'required|numeric|min:0',
            'Payment_method' => 'required|in:Cash,Card,GCash',
        ]);

        return DB::transaction(function () use ($validated) {
            // Check stock availability before committing to the sale
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['ProductID']);
                if (($product->inventory?->QuantityOnHand ?? 0) < $item['Quantity']) {
                    abort(422, "Insufficient stock for {$product->Product_Name}.");
                }
            }

            $order = Order::create([
                'CustomerName' => 'Walk-in',
                'Order_Date' => now(),
                'Payment_status' => 'Paid',
                'Status' => 'Completed',
                'CreatedBy' => auth()->id(),
            ]);

            $total = 0;
            foreach ($validated['items'] as $item) {
                $order->orderItems()->create([
                    'ProductID' => $item['ProductID'],
                    'Quantity' => $item['Quantity'],
                    'Status' => 'Fulfilled',
                ]);

                // Deduct inventory immediately since POS sales are instant handoffs
                Product::find($item['ProductID'])->inventory?->deduct($item['Quantity']);

                $total += $item['Quantity'] * $item['UnitPrice'];
            }

            $transaction = Transaction::create([
                'OrderID' => $order->OrderID,
                'TransactionDate' => now(),
                'TotalAmount' => $total,
                'Payment_method' => $validated['Payment_method'],
                'created_by' => auth()->id(),
            ]);

            return $transaction->load('order.orderItems.product');
        });
    }
    public function dailyTotal(Request $request){
        $date = $request->date ?? today();
        return [
            'date' => $date,
            'total' => Transaction::whereDate('TransactionDate', $date)->sum('TotalAmount'),
            'count' => Transaction::whereDate('TransactionDate', $date)->count(),
        ];
    }
    public function byPaymentMethod(Request $request){
        return Transaction::query()
            ->when($request->date, fn($q, $date) => $q->whereDate('TransactionDate', $date))
            ->selectRaw('Payment_method, COUNT(*) as count, SUM(TotalAmount) as total')
            ->groupBy('Payment_method')
            ->get();
    }
}
