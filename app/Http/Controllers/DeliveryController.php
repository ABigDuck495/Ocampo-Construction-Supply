<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OrderController;
use App\Models\Delivery;
use App\Models\Dispatch;
use App\Models\DispatchLog;
use App\Models\Driver;
use App\Models\OrderItem;
use App\Models\Truck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    public function index()
    {
        $orders = OrderItem::with(['order', 'product'])
            ->whereHas('order', function ($q) {
                $q->whereNotIn('Status', ['Completed', 'Cancelled']);
            })
            ->get();

        $trucks = Truck::with(['dispatches.orderItem.order', 'dispatches.orderItem.product', 'dispatches.drivers'])->get();
        $drivers = Driver::all();

        $systemSettings = DB::table('system_settings')
                ->pluck('Setting_Value', 'Setting_Key')
                ->toArray();

        return view('deliveries.index', compact('orders', 'trucks', 'systemSettings', 'drivers'));
    }

    public function create()
    {
        return response()->json(['message' => 'Create delivery endpoint.'], 200);
    }

    /**
     * Called when the driver marks a dispatch as delivered or failed on
     * their device. QuantityDelivered is the real quantity the customer
     * actually received — if it's less than what was dispatched (pasabay),
     * the shortfall is released back into the pending pool automatically.
     */
    public function store(Request $request, Dispatch $dispatch)
    {
        $validated = $request->validate([
            'QuantityDelivered' => 'required|integer|min:0',
            'Status' => 'required|in:Delivered,Failed',
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

            if ($validated['Status'] === 'Failed') {
                $dispatch->update(['Status' => 'Failed']);
                DispatchController::releaseTruckIfClear($dispatch->TruckID);

                DispatchLog::create([
                    'DispatchID' => $dispatch->DispatchID,
                    'Action' => 'Failed',
                    'Notes' => $validated['Notes'] ?? 'Delivery attempt failed; items returned to pending pool.',
                    'LoggedAt' => now(),
                ]);

                return $delivery->load('dispatch');
            }

            $originalQty = $dispatch->QuantityDispatched;
            $deliveredQty = $validated['QuantityDelivered'];
            $wasPartial = $deliveredQty < $originalQty;

            // Shrink the dispatch to the real delivered amount so the
            // undelivered remainder is automatically freed back into the
            // pending pool (pasabay) via the existing quantity-remaining queries.
            $dispatch->update([
                'Status' => 'Delivered',
                'QuantityDispatched' => $deliveredQty,
            ]);
            DispatchController::releaseTruckIfClear($dispatch->TruckID);

            $product = $dispatch->orderItem->product;
            $product->inventory?->deduct($deliveredQty);

            DispatchLog::create([
                'DispatchID' => $dispatch->DispatchID,
                'Action' => $wasPartial ? 'PartiallyDelivered' : 'Delivered',
                'Notes' => $wasPartial
                    ? "Delivered {$deliveredQty} of {$originalQty}; remaining " . ($originalQty - $deliveredQty) . " item(s) returned to pending pool."
                    : 'Delivered in full.',
                'LoggedAt' => now(),
            ]);

            $orderItem = $dispatch->orderItem;
            if ($orderItem->quantityRemaining() <= 0) {
                $orderItem->update(['Status' => OrderItem::STATUS_COMPLETED]);
            } else {
                $orderItem->update(['Status' => OrderItem::STATUS_IN_PROGRESS]);
            }

            app(OrderController::class)->syncStatus($orderItem->order);

            return $delivery->load('dispatch');
        });
    }

    public function show(string $id)
    {
        return Delivery::with('dispatch.orderItem.order')->findOrFail($id);
    }

    public function edit(string $id)
    {
        return Delivery::with('dispatch.orderItem.order')->findOrFail($id);
    }

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