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
