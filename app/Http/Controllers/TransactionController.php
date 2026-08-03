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
        return Transaction::query()
            ->with('order')
            ->latest('TransactionDate')
            ->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Create transaction endpoint.'], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'OrderID' => 'required|exists:orders,OrderID',
            'TransactionDate' => 'nullable|date',
            'Amount' => 'nullable|numeric',
            'PaymentMethod' => 'nullable|in:Cash,Credit,Cash On Delivery',
        ]);

        return Transaction::create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Transaction::with('order')->findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return Transaction::with('order')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'OrderID' => 'sometimes|required|exists:orders,OrderID',
            'TransactionDate' => 'nullable|date',
            'Amount' => 'nullable|numeric',
            'PaymentMethod' => 'nullable|in:Cash,Credit,Cash On Delivery',
        ]);

        $transaction = Transaction::findOrFail($id);
        $transaction->fill($validated);
        $transaction->save();

        return $transaction->load('order');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $transaction = Transaction::findOrFail($id);
        $transaction->delete();

        return response()->json(['message' => 'Transaction deleted successfully.'], 200);
    }
    public function posSale(Request $request){
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.ProductID' => 'required|exists:products,ProductID',
            'items.*.Quantity' => 'required|string|max:50',
            'items.*.UnitPrice' => 'required|numeric|min:0',
            'PaymentMethod' => 'required|in:Cash,Credit,Cash On Delivery',
        ]);

        return DB::transaction(function () use ($validated) {
            // Check stock availability before committing to the sale
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['ProductID']);
                $qtyValue = (float) $item['Quantity'];
                if (($product->inventory?->QuantityOnHand ?? 0) < $qtyValue) {
                    abort(422, "Insufficient stock for {$product->Product_Name}.");
                }
            }

            $order = Order::create([
                'CustomerName' => 'Walk-in',
                'OrderDate' => now(),
                'PaymentStatus' => 'Paid',
                'Status' => 'Completed',
                'CreatedBy' => auth()->id(),
            ]);

            $total = 0;
            foreach ($validated['items'] as $item) {
                $qtyValue = (float) $item['Quantity'];

                $order->orderItems()->create([
                    'ProductID' => $item['ProductID'],
                    'Quantity' => $item['Quantity'], // stored as typed, e.g. "5kg"
                    'Status' => 'Fulfilled',
                ]);

                // Deduct inventory immediately since POS sales are instant handoffs
                Product::find($item['ProductID'])->inventory?->deduct($qtyValue);

                $total += $qtyValue * $item['UnitPrice'];
            }

            $transaction = Transaction::create([
                'OrderID' => $order->OrderID,
                'TransactionDate' => now(),
                'Amount' => $total,
                'PaymentMethod' => $validated['PaymentMethod'],
            ]);

            return $transaction->load('order.orderItems.product');
        });
    }
    public function dailyTotal(Request $request){
        $date = $request->date ?? today();
        return [
            'date' => $date,
            'total' => Transaction::whereDate('TransactionDate', $date)->sum('Amount'),
            'count' => Transaction::whereDate('TransactionDate', $date)->count(),
        ];
    }
    public function byPaymentMethod(Request $request){
        return Transaction::query()
            ->when($request->date, fn($q, $date) => $q->whereDate('TransactionDate', $date))
            ->selectRaw('PaymentMethod, COUNT(*) as count, SUM(Amount) as total')
            ->groupBy('PaymentMethod')
            ->get();
    }
}
