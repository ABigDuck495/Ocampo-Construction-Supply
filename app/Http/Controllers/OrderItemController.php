<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
            'Quantity' => 'required|numeric|min:0.01',
            'UnitPrice' => 'nullable|numeric|min:0',
            'Pricing_method' => ['nullable', 'string', Rule::in(['Fixed','Variable'])],
            'Pricing_status' => ['nullable', 'string', Rule::in(['Resolved','Unresolved'])],
            'Status' => ['nullable', 'string', Rule::in(array_merge(OrderItem::allowedStatuses(), ['Partially Fulfilled', 'Fulfilled']))],
        ]);

        // If attempting to mark as Resolved, ensure a UnitPrice exists or product has a price
        if (! empty($validated['Pricing_status']) && $validated['Pricing_status'] === 'Resolved') {
            $hasPrice = isset($validated['UnitPrice']) && is_numeric($validated['UnitPrice']);
            if (! $hasPrice) {
                $product = \App\Models\Product::find($validated['ProductID']);
                if (! ($product && $product->UnitPrice !== null)) {
                    return response()->json(['message' => 'Cannot mark pricing as Resolved without a unit price.'], 422);
                }
            }
        }

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
            'Quantity' => 'sometimes|required|numeric|min:0.01',
            'UnitPrice' => 'nullable|numeric|min:0',
            'Pricing_method' => ['nullable', 'string', Rule::in(['Fixed','Variable'])],
            'Pricing_status' => ['nullable', 'string', Rule::in(['Resolved','Unresolved'])],
            'Status' => ['sometimes', 'required', 'string', Rule::in(array_merge(OrderItem::allowedStatuses(), ['Partially Fulfilled', 'Fulfilled']))],
        ]);

        $orderItem = OrderItem::findOrFail($id);
        // Prevent marking as Resolved without a price
        if (array_key_exists('Pricing_status', $validated) && $validated['Pricing_status'] === 'Resolved') {
            $hasPrice = array_key_exists('UnitPrice', $validated) && is_numeric($validated['UnitPrice']);
            if (! $hasPrice && ($orderItem->UnitPrice === null && ($orderItem->product?->UnitPrice === null))) {
                return response()->json(['message' => 'Cannot mark pricing as Resolved without a unit price.'], 422);
            }
        }

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
        $request->validate(['Status' => ['required', 'string', Rule::in(array_merge(OrderItem::allowedStatuses(), ['Partially Fulfilled', 'Fulfilled']))]]);
        $orderItem->update(['Status' => $request->Status]);
        return $orderItem;
    }
}
