<?php

namespace App\Http\Controllers;

use App\Http\Controllers\OrderController;
use App\Models\Delivery;
use App\Models\Dispatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
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
            $dispatch->update(['Status' => 'Completed']);
            if ($validated['Status'] === 'Delivered') {
                $product = $dispatch->orderItem->product;
                $product->inventory?->deduct($validated['QuantityDelivered']);

                $orderItem = $dispatch->orderItem;
                if ($orderItem->quantityDispatched() >= $orderItem->Quantity) {
                    $orderItem->update(['Status' => 'Fulfilled']);
                } else {
                    $orderItem->update(['Status' => 'Partially Fulfilled']);
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
    public function failedDeliveries(){
        return Delivery::failed()->with('dispatch.orderItem.order')->get();
    }
}
