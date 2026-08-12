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
            'PaymentMethod' => 'nullable|in:COD,GCash,Card,Bank Transfer',
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
            'PaymentMethod' => 'nullable|in:COD,GCash,Card,Bank Transfer',
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

    /**
     * Get a receipt-formatted view of the specified transaction.
     */
    public function receipt(string $id)
    {
        $transaction = Transaction::with('order.orderItems.product')->findOrFail($id);

        return response()->json([
            'receipt_no' => $transaction->TransactionID,
            'date' => $transaction->TransactionDate,
            'customer_name' => $transaction->order->CustomerName,
            'order_type' => $transaction->order->OrderType ?? 'POS',
            'payment_method' => $transaction->PaymentMethod,
            'payment_status' => $transaction->order->PaymentStatus,
            'items' => $transaction->order->orderItems->map(fn($item) => [
                'product' => $item->product->Product_Name,
                'quantity' => $item->Quantity,
                'status' => $item->Status,
            ]),
            'total' => $transaction->Amount,
        ]);
    }

    public function posSale(Request $request){
        // FIX: PaymentMethod list now matches what pos.js's payment
        // buttons actually send (COD, GCash, Card, Bank Transfer) instead
        // of the old Cash/Credit/Cash On Delivery list, which is why
        // every checkout was failing with "payment method is invalid."
        //
        // FIX: added OrderType, CustomerName, ContactNumber, Address,
        // Notes, PaymentStatus — pos.js has always sent these, but this
        // method previously ignored all of them and hardcoded the order
        // as a generic "Walk-in" / Completed sale, which is why delivery
        // orders never carried real customer details and never showed up
        // correctly on the Deliveries board.
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.ProductID' => 'required|exists:products,ProductID',
            'items.*.Quantity' => 'required|string|max:50',
            'items.*.UnitPrice' => 'required|numeric|min:0',
            'PaymentMethod' => 'required|in:COD,GCash,Card,Bank Transfer',
            'OrderType' => 'required|in:Delivery,Pickup',
            'CustomerName' => 'required|string',
            'ContactNumber' => 'nullable|string',
            'Address' => 'nullable|string',
            'Notes' => 'nullable|string',
            'PaymentStatus' => 'required|in:Paid,Unpaid',
        ]);

        $isPickup = $validated['OrderType'] === 'Pickup';

        return DB::transaction(function () use ($validated, $isPickup) {
            // Check stock availability before committing to the sale
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['ProductID']);
                $qtyValue = (float) $item['Quantity'];
                if (($product->inventory?->QuantityOnHand ?? 0) < $qtyValue) {
                    abort(422, "Insufficient stock for {$product->Product_Name}.");
                }
            }

            $order = Order::create([
                'CustomerName' => $validated['CustomerName'],
                'Address' => $validated['Address'] ?? null,
                'ContactNumber' => $validated['ContactNumber'] ?? null,
                'OrderDate' => now(),
                'PaymentStatus' => $validated['PaymentStatus'],
                'Status' => $isPickup ? 'Completed' : 'Pending',
                'OrderType' => $validated['OrderType'],
                'Notes' => $validated['Notes'] ?? null,
                'CreatedBy' => auth()->id(),
            ]);

            $total = 0;
            foreach ($validated['items'] as $item) {
                $qtyValue = (float) $item['Quantity'];

                $order->orderItems()->create([
                    'ProductID' => $item['ProductID'],
                    'Quantity' => $item['Quantity'], // stored as typed, e.g. "5kg"
                    'Status' => $isPickup ? 'Fulfilled' : 'Pending',
                ]);

                // FIX: deduct stock for every sale (Pickup and Delivery
                // alike), using deductQuantity — the method that actually
                // exists on the Inventory model (deduct() does not).
                Product::find($item['ProductID'])->inventory?->deductQuantity($qtyValue);

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