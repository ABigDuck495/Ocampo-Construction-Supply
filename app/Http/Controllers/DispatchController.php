<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Dispatch;
use App\Models\DispatchLog;
use App\Models\OrderItem;
use App\Models\Truck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\SystemSettings;

class DispatchController extends Controller
{
    public function index(){
        $orders = OrderItem::whereRaw('CAST(Quantity AS DECIMAL(10,2)) > (SELECT COALESCE(SUM(QuantityDispatched), 0) FROM dispatches WHERE dispatches.OrderItemID = order_items.OrderItemID AND dispatches.Status != \'Failed\')')
            ->with('product', 'order')
            ->get();
        $trucks = Truck::with('dispatches.orderItem.order', 'dispatches.orderItem.product', 'dispatches.drivers')->get();

        return view('deliveries.index', compact('orders', 'trucks'));
    }

    public function create()
    {
        return response()->json(['message' => 'Create dispatch endpoint.'], 200);
    }

    /**
     * Creates the dispatch as "Pending" — awaiting the driver's acceptance
     * on their device. Truck moves to "Loading" until accepted.
     */
    public function store(Request $request){
         $validated = $request->validate([
            'OrderItemID' => 'required|exists:order_items,OrderItemID',
            'TruckID' => 'required|exists:trucks,TruckID',
            'QuantityDispatched' => 'required|numeric|min:1',
            'DispatchDate' => 'required|date',
            'drivers' => 'required|array|min:1',
            'drivers.*.DriverID' => 'required|exists:drivers,DriverID',
            'drivers.*.Role' => 'required|in:Driver,Helper',
        ]);

        return DB::transaction(function () use ($validated) {
            $orderItem = OrderItem::findOrFail($validated['OrderItemID']);

            // Guard: don't dispatch more than what's left
            // Compare quantities with a 2-decimal tolerance to avoid float precision issues
            $remaining = round(max(0, $orderItem->quantityRemaining()), 2);
            $requested = round((float) $validated['QuantityDispatched'], 2);
            $tolerance = 0.01; // quantities are stored with 2 decimals
            if ($requested > $remaining + $tolerance) {
                abort(422, 'Quantity exceeds remaining order item quantity. Requested: ' . $requested . ' Remaining: ' . $remaining);
            }

            $dispatch = Dispatch::create([
                'OrderItemID' => $validated['OrderItemID'],
                'TruckID' => $validated['TruckID'],
                'DispatchDate' => $validated['DispatchDate'],
                'QuantityDispatched' => $validated['QuantityDispatched'],
                'Status' => 'Pending',
            ]);

            foreach ($validated['drivers'] as $driver) {
                $dispatch->drivers()->attach($driver['DriverID'], ['Role' => $driver['Role']]);
            }

            $dispatch->truck()->update(['Status' => 'Loading']);

            DispatchLog::create([
                'DispatchID' => $dispatch->DispatchID,
                'Action' => 'Dispatched',
                'Notes' => 'Assigned to truck, awaiting driver acceptance.',
                'LoggedAt' => now(),
            ]);

            return $dispatch->load('drivers', 'truck', 'orderItem');
        });
    }

    public function show(Dispatch $dispatch){
        return $dispatch->load('truck', 'drivers', 'orderItem.order', 'delivery', 'logs');
    }

    public function edit(string $id)
    {
        return Dispatch::with('truck', 'drivers', 'orderItem.product', 'delivery')->findOrFail($id);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'TruckID' => 'sometimes|required|exists:trucks,TruckID',
            'QuantityDispatched' => 'sometimes|required|integer|min:1',
            'DispatchDate' => 'sometimes|required|date',
            'Status' => 'sometimes|required|in:Pending,On Route,Delivered,Failed',
        ]);

        $dispatch = Dispatch::findOrFail($id);
        $dispatch->fill($validated);
        $dispatch->save();

        return $dispatch->load('truck', 'drivers', 'orderItem.product', 'delivery');
    }

    public function destroy(string $id)
    {
        $dispatch = Dispatch::findOrFail($id);
        $dispatch->delete();

        return response()->json(['message' => 'Dispatch deleted successfully.'], 200);
    }

    public function active(){
        return Dispatch::onRoute()->with('truck', 'drivers', 'orderItem.order')->get();
    }

    /**
     * Staff-side cancel (before or after acceptance). Frees the item back
     * into the pending pool and releases the truck if nothing else is on it.
     */
    public function cancel(Dispatch $dispatch){
        return DB::transaction(function () use ($dispatch) {
            $dispatch->update(['Status' => 'Failed']);
            self::releaseTruckIfClear($dispatch->TruckID);

            DispatchLog::create([
                'DispatchID' => $dispatch->DispatchID,
                'Action' => 'Failed',
                'Notes' => 'Cancelled by staff.',
                'LoggedAt' => now(),
            ]);

            return $dispatch;
        });
    }

    public function unassignedItems()
    {
        return OrderItem::whereRaw(
            'CAST(Quantity AS DECIMAL(10,2)) > (SELECT COALESCE(SUM(QuantityDispatched), 0) FROM dispatches WHERE dispatches.OrderItemID = order_items.OrderItemID AND dispatches.Status != \'Failed\')'
        )->with('product', 'order')->get();
    }

    /**
     * Sets a truck back to Idle once it has no more Pending or On Route
     * dispatches attached to it. Called after any dispatch is resolved
     * (delivered, partially delivered, failed, or cancelled).
     */
    public static function releaseTruckIfClear($truckId): void
    {
        $stillActive = Dispatch::where('TruckID', $truckId)
            ->whereIn('Status', ['Pending', 'On Route'])
            ->exists();

        if (!$stillActive) {
            Truck::where('TruckID', $truckId)->update(['Status' => 'Idle']);
        }
    }
}