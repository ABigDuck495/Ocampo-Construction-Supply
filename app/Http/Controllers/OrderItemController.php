<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return OrderItem::query()
            ->with('order', 'product')
            ->latest('OrderItemID')
            ->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Create order item endpoint.'], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'OrderID' => 'required|exists:orders,OrderID',
            'ProductID' => 'required|exists:products,ProductID',
            'Quantity' => 'required|integer|min:1',
            'Status' => 'nullable|string',
        ]);

        return OrderItem::create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return OrderItem::with('order', 'product')->findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return OrderItem::with('order', 'product')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'OrderID' => 'sometimes|required|exists:orders,OrderID',
            'ProductID' => 'sometimes|required|exists:products,ProductID',
            'Quantity' => 'sometimes|required|integer|min:1',
            'Status' => 'sometimes|required|string',
        ]);

        $orderItem = OrderItem::findOrFail($id);
        $orderItem->fill($validated);
        $orderItem->save();

        return $orderItem->load('order', 'product');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $orderItem = OrderItem::findOrFail($id);
        $orderItem->delete();

        return response()->json(['message' => 'Order item deleted successfully.'], 200);
    }

    public function remaining(OrderItem $orderItem)
    {
        return [
            'ordered' => $orderItem->Quantity,
            'dispatched' => $orderItem->quantityDispatched(),
            'remaining' => $orderItem->quantityRemaining(),
        ];
    }

    // Update fulfillment status for a specific line item
    public function updateStatus(Request $request, OrderItem $orderItem)
    {
        $request->validate(['Status' => 'required|in:Pending,Partially Fulfilled,Fulfilled']);
        $orderItem->update(['Status' => $request->Status]);
        return $orderItem;
    }
}
