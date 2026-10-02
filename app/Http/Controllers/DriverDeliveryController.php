<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Models\DispatchLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Driver-app endpoints. Kept separate from DeliveryController (staff CRUD
 * over the `deliveries` table) and DispatchController (staff dispatch
 * creation/management) to avoid clashing with either.
 *
 * NOTE ON GROUPING: staff create one dispatch per order item, so a single
 * order with 4 products on one truck becomes 4 dispatch rows. The driver
 * should see that as ONE delivery. Dispatches that share the same order,
 * truck and status (and are assigned to this driver) are treated as one
 * "truckload" group: the list shows one card per group, and accept/deliver
 * act on every dispatch in the group together. The app keeps using the
 * first dispatch's ID as the group's identifier, so no Flutter changes
 * are needed.
 */
class DriverDeliveryController extends Controller
{
    /**
     * Open deliveries for the logged-in driver (awaiting acceptance, or
     * accepted and on route), one entry per order/truck group.
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
            ->orderBy('DispatchID')
            ->get();

        $deliveries = $dispatches
            ->groupBy(fn ($d) => ($d->orderItem?->OrderID ?? 'x') . '|' . $d->TruckID . '|' . $d->Status)
            ->map(fn ($group) => $this->formatDispatch($group->first(), $driverId, $group))
            ->values();

        return response()->json(['deliveries' => $deliveries]);
    }

    /**
     * Full detail for one assigned delivery (group).
     *
     * GET /api/driver/deliveries/{dispatch}
     */
    public function show(Request $request, Dispatch $dispatch)
    {
        $driverId = $request->user()->DriverID;

        if (!$driverId || !$this->isAssignedToDriver($dispatch, $driverId)) {
            return response()->json(['message' => 'This delivery is not assigned to you.'], 403);
        }

        $group = $this->groupFor($dispatch, $driverId);

        return response()->json([
            'delivery' => $this->formatDispatch($group->first() ?? $dispatch, $driverId, $group),
        ]);
    }

    /**
     * Driver accepts a pending delivery assigned to them.
     * Every dispatch in the group: 'Pending' -> 'On Route'.
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

        $myPivot = $dispatch->drivers->firstWhere('DriverID', $driverId)?->pivot;

        if ($myPivot?->Role !== 'Driver') {
            return response()->json([
                'message' => 'Only the assigned driver can accept this delivery. Helpers cannot accept.',
            ], 403);
        }

        $group = $this->groupFor($dispatch, $driverId);

        return DB::transaction(function () use ($group, $dispatch) {
            // One shared timestamp for the whole truckload, so the receipt can
            // later tell which dispatches left together.
            $acceptedAt = now();

            foreach ($group as $d) {
                $d->update([
                    'Status' => 'On Route',
                    'AcceptedAt' => $acceptedAt,
                ]);

                DispatchLog::create([
                    'DispatchID' => $d->DispatchID,
                    'Action' => 'Accepted',
                    'Notes' => 'Driver accepted and departed.',
                    'LoggedAt' => $acceptedAt,
                ]);
            }

            $dispatch->truck()->update(['Status' => 'On Route']);

            return response()->json(['message' => 'Delivery accepted.']);
        });
    }

    /**
     * Driver marks an accepted/on-route delivery as delivered.
     * Every dispatch in the group is completed together.
     *
     * Body (optional): {
     *   "items": [{"DispatchID": 12, "QuantityDelivered": 9}, ...],
     *   "Notes": "customer not home for 2 bags"
     * }
     * If "items" is omitted, every dispatch in the group is delivered in
     * full (legacy behavior, still used by anything that just POSTs here
     * with no body).
     *
     * POST /api/driver/deliveries/{dispatch}/deliver
     */
    public function deliver(Request $request, Dispatch $dispatch)
    {
        $driverId = $request->user()->DriverID;

        if (!$driverId) {
            return response()->json([
                'message' => 'This account is not linked to a driver profile.',
            ], 403);
        }

        if (!$this->isAssignedToDriver($dispatch, $driverId)) {
            return response()->json([
                'message' => 'This delivery is not assigned to you.',
            ], 403);
        }

        if ($dispatch->Status !== 'On Route') {
            return response()->json([
                'message' => 'The delivery must be accepted before it can be marked as delivered.',
            ], 409);
        }

        if ($dispatch->delivery()->exists()) {
            return response()->json([
                'message' => 'This delivery has already been completed.',
            ], 409);
        }

        $group = $this->groupFor($dispatch, $driverId);
        $groupIds = $group->pluck('DispatchID')->all();

        $validated = $request->validate([
            'items' => 'sometimes|array',
            'items.*.DispatchID' => 'required_with:items|integer',
            'items.*.QuantityDelivered' => 'required_with:items|integer|min:0',
            'Notes' => 'nullable|string',
        ]);

        // Map submitted quantities by dispatch id, rejecting anything that
        // doesn't belong to this delivery's group.
        $quantities = [];
        foreach ($validated['items'] ?? [] as $item) {
            $itemDispatchId = (int) $item['DispatchID'];

            if (!in_array($itemDispatchId, $groupIds, true)) {
                abort(422, 'One of the submitted items does not belong to this delivery.');
            }

            $quantities[$itemDispatchId] = (int) $item['QuantityDelivered'];
        }

        $notes = $validated['Notes'] ?? 'Delivered by driver through the mobile app.';

        // All-or-nothing: if any dispatch in the group fails, none are marked.
        $outcomes = DB::transaction(function () use ($group, $quantities, $notes) {
            $outcomes = [];

            foreach ($group as $d) {
                if ($d->delivery()->exists()) {
                    continue;
                }

                // Fall back to the full dispatched quantity (cast, since the
                // stored value may come back as "11.00", which fails the
                // 'integer' validation rule) when no quantity was submitted
                // for this dispatch.
                $qty = $quantities[$d->DispatchID]
                    ?? (int) round((float) $d->QuantityDispatched);

                // Use the existing DeliveryController so all existing delivery
                // business rules are applied consistently. It decides whether
                // this line is Delivered, Partial (short quantity) or Failed
                // (nothing delivered) from the quantity submitted.
                $deliveryRequest = Request::create(
                    "/api/driver/deliveries/{$d->DispatchID}/deliver",
                    'POST',
                    [
                        'QuantityDelivered' => $qty,
                        'Status' => 'Delivered',
                        'Notes' => $notes,
                    ]
                );

                app(DeliveryController::class)->store($deliveryRequest, $d);

                $outcomes[] = $d->fresh()->Status;

                $order = $d->orderItem?->order;
                if ($order) {
                    $order->update(['PaymentStatus' => 'Paid']);
                }
            }

            return $outcomes;
        });

        $status = $this->overallStatus($outcomes);

        $message = match ($status) {
            'Partial' => 'Delivery marked as partial. The undelivered items were returned to the pending pool.',
            'Failed' => 'Nothing was delivered. All items were returned to the pending pool.',
            default => 'Delivery marked as delivered.',
        };

        return response()->json([
            'message' => $message,
            'dispatch_id' => $dispatch->DispatchID,
            'status' => $status,
        ]);
    }

    /**
     * Delivery receipt. The header (truck/crew/date) is this dispatch, but
     * the item list pulls in every non-failed dispatch under the same order,
     * so a customer who received several truckloads sees the full manifest.
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

        // Every dispatch under the same order (any truck/driver), excluding
        // failed/cancelled ones, so the receipt shows the real manifest.
        $orderDispatches = Dispatch::with(['orderItem.product', 'delivery'])
            ->where('Status', '!=', 'Failed')
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
                    // A Partial dispatch shows what was actually delivered.
                    'quantity' => $group->sum(fn ($d) => $d->Status === 'Partial'
                        ? (float) ($d->delivery?->QuantityDelivered ?? 0)
                        : (float) $d->QuantityDispatched),
                ];
            })
            ->values();

        // While this truckload is still open (Pending / On Route), "Items
        // Loaded" must show only what is on THIS truck, using the quantity
        // dispatched for it. Otherwise a re-dispatched shortfall (e.g. 1 pipe)
        // gets added to the earlier partial delivery (16) and shows 17, and
        // items from earlier truckloads show up too. Once the delivery is
        // resolved, the full order manifest above is used.
        if (in_array($dispatch->Status, ['Pending', 'On Route'], true)) {
            $items = $this->groupFor($dispatch, $driverId)
                ->filter(fn ($d) => $d->orderItem && $d->orderItem->product)
                ->groupBy('OrderItemID')
                ->map(function ($g) {
                    $product = $g->first()->orderItem->product;
                    return [
                        'product_name' => $product->Product_Name,
                        'unit' => $product->Unit,
                        'quantity' => $g->sum(fn ($d) => (float) $d->QuantityDispatched),
                    ];
                })
                ->values();
        }

        $crew = $dispatch->drivers->map(fn ($d) => [
            'driver_id' => $d->DriverID,
            'name' => $d->Name,
            'role' => $d->pivot->Role,
        ]);

        $deliveredAt = $dispatch->delivery?->DeliveryDate ?? $dispatch->DispatchDate;

        // Editable line items for the "Confirm Delivery" dialog: only the
        // dispatches in THIS on-route truckload, each carrying its own
        // dispatch id and the max (dispatched) quantity that can be
        // confirmed. Empty once the delivery is completed.
        $pendingItems = collect();

        if ($dispatch->Status === 'On Route') {
            $group = $this->groupFor($dispatch, $driverId);

            $pendingItems = $group
                ->filter(fn ($d) => $d->orderItem && $d->orderItem->product && !$d->delivery()->exists())
                ->map(fn ($d) => [
                    'dispatch_id' => $d->DispatchID,
                    'product_name' => $d->orderItem->product->Product_Name,
                    'unit' => $d->orderItem->product->Unit,
                    'quantity_dispatched' => $d->QuantityDispatched,
                ])
                ->values();
        }

        return response()->json([
            'receipt' => [
                'dispatch_id' => $dispatch->DispatchID,
                'order_id' => $order->OrderID,
                'dispatch_status' => $this->receiptStatus($dispatch, $order->OrderID),
                'delivered_at' => optional($deliveredAt)->toIso8601String(),
                'truck_name' => $dispatch->truck?->TruckName,
                'plate_number' => $dispatch->truck?->PlateNumber,
                'crew' => $crew,
                'customer_name' => $order->CustomerName,
                'address' => $order->Address,
                'items' => $items,
                'pending_items' => $pendingItems,
            ],
        ]);
    }

    /**
     * Collapses the per-line outcomes of one truckload into the status shown
     * on the receipt: all Delivered -> Delivered, all Failed -> Failed,
     * anything mixed or short -> Partial.
     */
    private function overallStatus(array $statuses): string
    {
        $statuses = array_values(array_unique($statuses));

        if ($statuses === ['Delivered']) {
            return 'Delivered';
        }

        if ($statuses === ['Failed']) {
            return 'Failed';
        }

        return 'Partial';
    }

    /**
     * Receipt status for a dispatch. Once resolved, the dispatches that
     * left together (same order, truck and AcceptedAt) are judged as one
     * truckload, so a short line marks the whole receipt "Partial".
     */
    private function receiptStatus(Dispatch $dispatch, $orderId): string
    {
        $resolved = ['Delivered', 'Partial', 'Failed'];

        if (!in_array($dispatch->Status, $resolved, true) || !$dispatch->AcceptedAt) {
            return $dispatch->Status;
        }

        $statuses = Dispatch::where('TruckID', $dispatch->TruckID)
            ->where('AcceptedAt', $dispatch->AcceptedAt)
            ->whereIn('Status', $resolved)
            ->whereHas('orderItem', fn ($q) => $q->where('OrderID', $orderId))
            ->pluck('Status')
            ->all();

        return $this->overallStatus($statuses ?: [$dispatch->Status]);
    }

    private function isAssignedToDriver(Dispatch $dispatch, $driverId): bool
    {
        return $dispatch->drivers()->where('drivers.DriverID', $driverId)->exists();
    }

    /**
     * All dispatches that belong to the same driver-facing delivery as the
     * given one: same order, same truck, same status, same driver.
     * Always includes the given dispatch itself.
     */
    private function groupFor(Dispatch $dispatch, $driverId)
    {
        $orderId = $dispatch->orderItem?->OrderID;

        $group = Dispatch::with(['orderItem.order', 'orderItem.product', 'truck', 'drivers'])
            ->where('TruckID', $dispatch->TruckID)
            ->where('Status', $dispatch->Status)
            ->whereHas('orderItem', fn ($q) => $q->where('OrderID', $orderId))
            ->whereHas('drivers', fn ($q) => $q->where('drivers.DriverID', $driverId))
            ->orderBy('DispatchID')
            ->get();

        return $group->isEmpty() ? collect([$dispatch]) : $group;
    }

    private function formatDispatch(Dispatch $dispatch, $driverId, $group = null): array
    {
        $group = $group ?? collect([$dispatch]);

        $orderItem = $dispatch->orderItem;
        $order = $orderItem?->order;
        $product = $orderItem?->product;
        $truck = $dispatch->truck;
        $myPivot = $dispatch->drivers->firstWhere('DriverID', $driverId)?->pivot;

        // Manifest for THIS truckload only (the group's dispatches), one line
        // per order item.
        $items = $group
            ->filter(fn ($d) => $d->orderItem && $d->orderItem->product)
            ->groupBy('OrderItemID')
            ->map(function ($g) {
                $itemProduct = $g->first()->orderItem->product;

                return [
                    'product_name' => $itemProduct->Product_Name,
                    'unit' => $itemProduct->Unit,
                    'quantity' => $g->sum('QuantityDispatched'),
                ];
            })
            ->values()
            ->all();

        return [
            'DispatchID' => $dispatch->DispatchID,
            'DispatchIDs' => $group->pluck('DispatchID')->values()->all(),
            'DispatchDate' => optional($dispatch->DispatchDate)->toIso8601String(),
            'QuantityDispatched' => $dispatch->QuantityDispatched,
            'DispatchStatus' => $dispatch->Status,
            'DriverRole' => $myPivot?->Role,
            // Only the main 'Driver' can accept; a 'Helper' can view but not accept.
            'CanAccept' => $myPivot?->Role === 'Driver',
            'OrderID' => $order?->OrderID,
            'CustomerName' => $order?->CustomerName,
            'Address' => $order?->Address,
            'ContactNumber' => $order?->ContactNumber,
            // Keep the original single-dispatch fields for compatibility.
            'Product_Name' => $product?->Product_Name,
            'Unit' => $product?->Unit,
            'TruckName' => $truck?->TruckName,
            'PlateNumber' => $truck?->PlateNumber,
            // Manifest for this truckload.
            'Items' => $items,
        ];
    }
}