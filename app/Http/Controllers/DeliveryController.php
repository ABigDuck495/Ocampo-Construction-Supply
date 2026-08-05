<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OrderController;
use App\Models\Delivery;
use App\Models\Dispatch;
use App\Models\OrderItem;
use App\Models\Truck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
{
    $orders = OrderItem::with(['order', 'product'])
        ->whereHas('order', function ($q) {
            $q->whereNotIn('Status', ['Completed', 'Cancelled']);
        })
        ->get();

    $trucks = Truck::with(['dispatches.drivers'])->get();

    return view('deliveries.index', compact('orders', 'trucks'));
}

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Create delivery endpoint.'], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Dispatch $dispatch)
    {
        $validated = $request->validate([
            'QuantityDelivered' => 'required|integer|min:0',
            'Status' => 'required|in:Delivered,Failed,Returned',
            'Notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $dispatch) {
            $delivery = Delivery::create([
                'DispatchID' => $dispatch->DispatchID,
                'DeliveryDate' => now(),
                'QuantityDelivered' => $validated['QuantityDelivered'],
                'Status' => $validated['Status'],
                'Notes' => $validated['Notes'] ?? null,
            ]);

            $dispatch->truck()->update(['Status' => 'Available']);
            $dispatch->update(['Status' => 'Delivered']);
            if ($validated['Status'] === 'Delivered') {
                $product = $dispatch->orderItem->product;
                $product->inventory?->deduct($validated['QuantityDelivered']);

                $orderItem = $dispatch->orderItem;
                if ($orderItem->quantityDispatched() >= $orderItem->Quantity) {
                    $orderItem->update(['Status' => OrderItem::STATUS_COMPLETED]);
                } else {
                    $orderItem->update(['Status' => OrderItem::STATUS_IN_PROGRESS]);
                }

                app(OrderController::class)->syncStatus($orderItem->order);
            }

            return $delivery->load('dispatch');
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Delivery::with('dispatch.orderItem.order')->findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return Delivery::with('dispatch.orderItem.order')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'QuantityDelivered' => 'sometimes|required|integer|min:0',
            'Status' => 'sometimes|required|in:Delivered,Failed,Returned',
            'Notes' => 'nullable|string',
        ]);

        $delivery = Delivery::findOrFail($id);
        $delivery->fill($validated);
        $delivery->save();

        return $delivery->load('dispatch');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $delivery = Delivery::findOrFail($id);
        $delivery->delete();

        return response()->json(['message' => 'Delivery deleted successfully.'], 200);
    }
    public function failedDeliveries(){
        return Delivery::failed()->with('dispatch.orderItem.order')->get();
    }
}
