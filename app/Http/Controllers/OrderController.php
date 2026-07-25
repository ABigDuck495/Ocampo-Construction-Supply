<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        return Order::query()
            ->when($request->status, fn($q, $status) => $q->where('Status', $status))
            ->when($request->date, fn($q, $date) => $q->whereDate('OrderDate', $date))
            ->with('orderItems.product')
            ->latest('OrderDate')
            ->paginate(20);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Create order endpoint.'], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request){
        $validated = $request->validate([
            'CustomerName' => 'required|string',
            'Address' => 'nullable|string',
            'ContactNumber' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.ProductID' => 'required|exists:products,ProductID',
            'items.*.Quantity' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $order = Order::create([
                'CustomerName' => $validated['CustomerName'],
                'Address' => $validated['Address'] ?? null,
                'ContactNumber' => $validated['ContactNumber'] ?? null,
                'Order_Date' => now(),
                'Payment_status' => 'Unpaid',
                'Status' => 'Pending',
                'CreatedBy' => auth()->id(),
            ]);

            foreach ($validated['items'] as $item) {
                $order->orderItems()->create([
                    'ProductID' => $item['ProductID'],
                    'Quantity' => $item['Quantity'],
                    'Status' => 'Pending',
                ]);
            }

            return $order->load('orderItems');
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Order $order)
    {
        return $order->load([
            'orderItems.product',
            'orderItems.dispatches.truck',
            'orderItems.dispatches.drivers',
            'orderItems.dispatches.delivery',
            'transactions',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return Order::with('orderItems.product')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'CustomerName' => 'sometimes|required|string',
            'Address' => 'nullable|string',
            'ContactNumber' => 'nullable|string',
            'Status' => 'nullable|string',
            'Payment_status' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.ProductID' => 'required_with:items|exists:products,ProductID',
            'items.*.Quantity' => 'required_with:items|integer|min:1',
        ]);

        return DB::transaction(function () use ($validated, $id) {
            $order = Order::findOrFail($id);

            $updateData = [];

            if (array_key_exists('CustomerName', $validated)) {
                $updateData['CustomerName'] = $validated['CustomerName'];
            }
            if (array_key_exists('Address', $validated)) {
                $updateData['Address'] = $validated['Address'];
            }
            if (array_key_exists('ContactNumber', $validated)) {
                $updateData['ContactNumber'] = $validated['ContactNumber'];
            }
            if (array_key_exists('Status', $validated)) {
                $updateData['Status'] = $validated['Status'];
            }
            if (array_key_exists('Payment_status', $validated)) {
                $updateData['Payment_status'] = $validated['Payment_status'];
            }

            if (! empty($updateData)) {
                $order->update($updateData);
            }

            if (! empty($validated['items'] ?? [])) {
                $order->orderItems()->delete();

                foreach ($validated['items'] as $item) {
                    $order->orderItems()->create([
                        'ProductID' => $item['ProductID'],
                        'Quantity' => $item['Quantity'],
                        'Status' => 'Pending',
                    ]);
                }
            }

            return $order->load('orderItems');
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $order = Order::findOrFail($id);
        $order->delete();

        return response()->json(['message' => 'Order deleted successfully.'], 200);
    }
    public function updateStatus(Order $order){
        if ($order->isFullyDelivered()) {
            $order->update(['Status' => 'Completed']);
        } elseif ($order->orderItems->contains(fn($i) => $i->quantityDispatched() > 0)) {
            $order->update(['Status' => 'Partially Fulfilled']);
        }
        return $order;
    }
    public function syncStatus(Order $order){
        if ($order->isFullyDelivered()) {
            $order->update(['Status' => 'Completed']);
        } elseif ($order->orderItems->contains(fn($i) => $i->quantityDispatched() > 0)) {
            $order->update(['Status' => 'Partially Fulfilled']);
        }
        return $order;
    }
}
