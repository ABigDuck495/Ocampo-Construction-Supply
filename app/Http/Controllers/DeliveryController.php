<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Controllers\OrderController;
use App\Models\Delivery;
use App\Models\Dispatch;
use App\Models\DispatchLog;
use App\Models\Driver;
use App\Models\OrderItem;
use App\Models\SystemSetting;
use App\Models\Truck;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\SystemSettings;

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

        $systemSettings = SystemSetting::allCached();

        return view('deliveries.index', compact('orders', 'trucks', 'systemSettings', 'drivers'));
    }

    public function create()
    {
        return response()->json(['message' => 'Create delivery endpoint.'], 200);
    }

    /**
     * Called when the driver confirms a dispatch on their device.
     * QuantityDelivered is the real quantity the customer actually received,
     * and the final status is worked out from it:
     *
     *   delivered >= dispatched      -> Delivered
     *   0 < delivered < dispatched   -> Partial (shortfall goes back into the
     *                                   pending pool to be dispatched again)
     *   delivered = 0                -> Failed (whole quantity goes back)
     */
    public function store(Request $request, Dispatch $dispatch)
    {
        $validated = $request->validate([
            'QuantityDelivered' => 'required|integer|min:0',
            'Status' => 'required|in:Delivered,Partial,Failed',
            'Notes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($validated, $dispatch) {
            $dispatched = (float) $dispatch->QuantityDispatched;
            $quantity = min((float) $validated['QuantityDelivered'], $dispatched);

            $status = $validated['Status'];
            if ($status !== 'Failed') {
                if ($quantity <= 0) {
                    $status = 'Failed';
                } elseif ($quantity < $dispatched) {
                    $status = 'Partial';
                } else {
                    $status = 'Delivered';
                }
            }

            $delivery = Delivery::create([
                'DispatchID' => $dispatch->DispatchID,
                'DeliveryDate' => now(),
                'QuantityDelivered' => $quantity,
                'Status' => $status,
                'Notes' => $validated['Notes'] ?? null,
            ]);

            if ($status === 'Failed') {
                $dispatch->update(['Status' => 'Failed']);
                DispatchController::releaseTruckIfClear($dispatch->TruckID);

                $orderItem = $dispatch->orderItem;
                if ($orderItem) {
                    $orderItem->recalculateStatus();
                    app(OrderController::class)->syncStatus($orderItem->order);
                }

                DispatchLog::create([
                    'DispatchID' => $dispatch->DispatchID,
                    'Action' => 'Failed',
                    'Notes' => $validated['Status'] === 'Failed'
                        ? ($validated['Notes'] ?? 'Delivery attempt failed; items returned to pending pool.')
                        : 'Nothing was delivered; items returned to pending pool.',
                    'LoggedAt' => now(),
                ]);

                return $delivery->load('dispatch');
            }

            // Respect system settings: inventory tracking and capacity enforcement
            $settings = new SystemSettings();

            $dispatch->update(['Status' => $status]);
            DispatchController::releaseTruckIfClear($dispatch->TruckID);

            $product = $dispatch->orderItem->product;
            if ($settings->isEnabled('enable_Inventory_tracking')) {
                // If tracking enabled, deduct only what was really delivered
                try {
                    $product->inventory?->deductQuantity($quantity);
                } catch (\Exception $e) {
                    if ($settings->isEnabled('allow_unresolved_price_checkout')) {
                        // swallow and continue if allowed by settings
                    } else {
                        throw $e;
                    }
                }
            }

            $orderItem = $dispatch->orderItem;
            $orderItem->recalculateStatus();

            app(OrderController::class)->syncStatus($orderItem->order);

            if ($status === 'Partial') {
                $shortfall = $dispatched - $quantity;

                DispatchLog::create([
                    'DispatchID' => $dispatch->DispatchID,
                    'Action' => 'PartiallyDelivered',
                    'Notes' => "Delivered {$quantity} of {$dispatched}; remaining {$shortfall} returned to pending pool.",
                    'LoggedAt' => now(),
                ]);
            }

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
            'Status' => 'sometimes|required|in:Delivered,Partial,Failed,Returned',
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