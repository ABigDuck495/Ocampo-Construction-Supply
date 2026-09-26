<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use Illuminate\Http\Request;

/**
 * Driver-app endpoints. Kept separate from DeliveryController (staff CRUD
 * over the `deliveries` table) and DispatchController (staff dispatch
 * creation/management) to avoid clashing with either.
 */
class DriverDeliveryController extends Controller
{
    /**
     * Dispatches assigned to the logged-in driver that are still open
     * (awaiting acceptance, or already accepted and on route).
     *
     * GET /api/driver/deliveries
     */
    public function index(Request $request)
    {
        $driverId = $request->user()->DriverID;

        if (!$driverId) {
            return response()->json([
                'message' => 'This account is not linked to a driver profile.',
            ], 403);
        }

        $dispatches = Dispatch::with(['orderItem.order', 'orderItem.product', 'truck', 'drivers'])
            ->whereHas('drivers', fn ($q) => $q->where('drivers.DriverID', $driverId))
            ->whereIn('Status', ['Pending', 'On Route'])
            ->orderByRaw("FIELD(Status, 'On Route', 'Pending')")
            ->orderBy('DispatchDate')
            ->get();

        return response()->json([
            'deliveries' => $dispatches->map(fn ($d) => $this->formatDispatch($d, $driverId)),
        ]);
    }

    /**
     * Full detail for one assigned dispatch.
     *
     * GET /api/driver/deliveries/{dispatch}
     */
    public function show(Request $request, Dispatch $dispatch)
    {
        $driverId = $request->user()->DriverID;

        if (!$driverId || !$this->isAssignedToDriver($dispatch, $driverId)) {
            return response()->json(['message' => 'This delivery is not assigned to you.'], 403);
        }

        $dispatch->load(['orderItem.order', 'orderItem.product', 'truck', 'drivers']);

        return response()->json([
            'delivery' => $this->formatDispatch($dispatch, $driverId),
        ]);
    }

    /**
     * Driver accepts a pending delivery assigned to them.
     * Dispatch: 'Pending' -> 'On Route'. Truck was already 'Unavailable'
     * since the dispatch was created, so it doesn't need to change here.
     *
     * POST /api/driver/deliveries/{dispatch}/accept
     */
    public function accept(Request $request, Dispatch $dispatch)
    {
        $driverId = $request->user()->DriverID;

        if (!$driverId) {
            return response()->json([
                'message' => 'This account is not linked to a driver profile.',
            ], 403);
        }

        if (!$this->isAssignedToDriver($dispatch, $driverId)) {
            return response()->json(['message' => 'This delivery is not assigned to you.'], 403);
        }

        if ($dispatch->Status !== 'Pending') {
            return response()->json([
                'message' => 'This delivery has already been accepted or completed.',
            ], 409);
        }

        $dispatch->update(['Status' => 'On Route']);

        return response()->json(['message' => 'Delivery accepted.']);
    }

    /**
     * Delivery receipt for a dispatch. The header (truck/crew/date) is this
     * specific dispatch, but the item list pulls in every other dispatch
     * under the same order, so a customer who received several truckloads
     * for one order sees the full manifest, not just this one product.
     *
     * GET /api/driver/deliveries/{dispatch}/receipt
     */
    public function receipt(Request $request, Dispatch $dispatch)
    {
        $driverId = $request->user()->DriverID;

        if (!$driverId || !$this->isAssignedToDriver($dispatch, $driverId)) {
            return response()->json(['message' => 'This delivery is not assigned to you.'], 403);
        }

        $dispatch->load(['orderItem.order', 'truck', 'drivers', 'delivery']);
        $order = $dispatch->orderItem?->order;

        if (!$order) {
            return response()->json(['message' => 'Delivery not found.'], 404);
        }

        // Every dispatch under the same order (any truck/driver), so the
        // receipt shows the full manifest, not just this one truckload.
        $orderDispatches = Dispatch::with(['orderItem.product'])
            ->whereHas('orderItem', fn ($q) => $q->where('OrderID', $order->OrderID))
            ->get();

        $items = $orderDispatches
            ->filter(fn ($d) => $d->orderItem && $d->orderItem->product)
            ->groupBy('OrderItemID')
            ->map(function ($group) {
                $product = $group->first()->orderItem->product;
                return [
                    'product_name' => $product->Product_Name,
                    'unit' => $product->Unit,
                    'quantity' => $group->sum('QuantityDispatched'),
                ];
            })
            ->values();

        $crew = $dispatch->drivers->map(fn ($d) => [
            'driver_id' => $d->DriverID,
            'name' => $d->Name,
            'role' => $d->pivot->Role,
        ]);

        $deliveredAt = $dispatch->delivery?->DeliveryDate ?? $dispatch->DispatchDate;

        return response()->json([
            'receipt' => [
                'dispatch_id' => $dispatch->DispatchID,
                'order_id' => $order->OrderID,
                'delivered_at' => optional($deliveredAt)->toIso8601String(),
                'truck_name' => $dispatch->truck?->TruckName,
                'plate_number' => $dispatch->truck?->PlateNumber,
                'crew' => $crew,
                'customer_name' => $order->CustomerName,
                'address' => $order->Address,
                'items' => $items,
            ],
        ]);
    }

    private function isAssignedToDriver(Dispatch $dispatch, $driverId): bool
    {
        return $dispatch->drivers()->where('drivers.DriverID', $driverId)->exists();
    }

    private function formatDispatch(Dispatch $dispatch, $driverId): array
    {
        $orderItem = $dispatch->orderItem;
        $order = $orderItem?->order;
        $product = $orderItem?->product;
        $truck = $dispatch->truck;
        $myPivot = $dispatch->drivers->firstWhere('DriverID', $driverId)?->pivot;

        return [
            'DispatchID' => $dispatch->DispatchID,
            'DispatchDate' => optional($dispatch->DispatchDate)->toIso8601String(),
            'QuantityDispatched' => $dispatch->QuantityDispatched,
            'DispatchStatus' => $dispatch->Status,
            'DriverRole' => $myPivot?->Role,
            'OrderID' => $order?->OrderID,
            'CustomerName' => $order?->CustomerName,
            'Address' => $order?->Address,
            'ContactNumber' => $order?->ContactNumber,
            'Product_Name' => $product?->Product_Name,
            'Unit' => $product?->Unit,
            'TruckName' => $truck?->TruckName,
            'PlateNumber' => $truck?->PlateNumber,
        ];
    }
}