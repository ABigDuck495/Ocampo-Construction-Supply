<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispatchController extends Controller
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
    public function store(Request $request){
         $validated = $request->validate([
            'OrderItemID' => 'required|exists:order_items,OrderItemID',
            'TruckID' => 'required|exists:trucks,TruckID',
            'QuantityDispatched' => 'required|integer|min:1',
            'DispatchDate' => 'required|date',
            'drivers' => 'required|array|min:1',
            'drivers.*.DriverID' => 'required|exists:drivers,DriverID',
            'drivers.*.Role' => 'required|in:Main,Assistant',
        ]);

        return DB::transaction(function () use ($validated) {
            $orderItem = OrderItem::findOrFail($validated['OrderItemID']);

            // Guard: don't dispatch more than what's left
            if ($validated['QuantityDispatched'] > $orderItem->quantityRemaining()) {
                abort(422, 'Quantity exceeds remaining order item quantity.');
            }

            $dispatch = Dispatch::create([
                'OrderItemID' => $validated['OrderItemID'],
                'TruckID' => $validated['TruckID'],
                'DispatchDate' => $validated['DispatchDate'],
                'QuantityDispatched' => $validated['QuantityDispatched'],
                'Status' => 'Dispatched',
            ]);

            foreach ($validated['drivers'] as $driver) {
                $dispatch->drivers()->attach($driver['DriverID'], ['Role' => $driver['Role']]);
            }

            $dispatch->truck()->update(['Status' => 'On Route']);

            return $dispatch->load('drivers', 'truck', 'orderItem');
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(Dispatch $dispatch){
        return $dispatch->load('truck', 'drivers', 'orderItem.order', 'delivery');
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
    public function active(){
        return Dispatch::onRoute()->with('truck', 'drivers', 'orderItem.order')->get();
    }
    public function cancel(Dispatch $dispatch){
        $dispatch->update(['Status' => 'Cancelled']);
        $dispatch->truck()->update(['Status' => 'Available']);
        return $dispatch;
    }
}
