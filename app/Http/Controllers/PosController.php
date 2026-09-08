<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PosController extends Controller
{
    public function index(){
        // FIX: inventory relation must be eager-loaded, otherwise
        // window.POS_DATA.products has no `inventory` key at all and
        // pos.js's `p.inventory ? Number(p.inventory.QuantityOnHand) : 0`
        // always falls back to 0.
        $products = Product::with('inventory')->get();
        $systemSettings = 
            DB::table('system_settings')
                ->pluck('Setting_Value', 'Setting_Key')
                ->toArray();

        return view('pos.index', compact('products', 'systemSettings'));
    }

    public function posSale(Request $request){
        $systemSettings = DB::table('system_settings')
                ->pluck('Setting_Value', 'Setting_Key')
                ->toArray();

        $allowUnresolved = ($systemSettings['allow_unresolved_price_checkout'] ?? 'false') === 'true';

        $rules = [
            'items' => 'required|array|min:1',
            'items.*.ProductID' => 'required|exists:products,ProductID',
            'items.*.Quantity' => 'required|string|max:50',
            'PaymentMethod' => 'required|in:COD,GCash,Card,Bank Transfer',
            'OrderType' => 'required|in:Delivery,Pickup',
            'CustomerName' => 'required|string',
            'ContactNumber' => 'nullable|string',
            'Address' => 'nullable|string',
            'Notes' => 'nullable|string',
            'PaymentStatus' => 'required|in:Paid,Unpaid',
        ];

        if ($allowUnresolved) {
            $rules['items.*.UnitPrice'] = 'nullable|numeric|min:0';
        } else {
            $rules['items.*.UnitPrice'] = 'required|numeric|min:0';
        }

        $validated = $request->validate($rules);

        $isPickup = $validated['OrderType'] === 'Pickup';

        $inventoryTrackingEnabled = ($systemSettings['enable_inventory_tracking'] ?? 'true') === 'true';

        return DB::transaction(function () use ($validated, $isPickup) {
            foreach ($validated['items'] as $item) {
                $product = Product::findOrFail($item['ProductID']);
                $qtyValue = (float) $item['Quantity'];
                if ($inventoryTrackingEnabled && (($product->inventory?->QuantityOnHand ?? 0) < $qtyValue)) {
                    abort(422, "Insufficient stock for {$product->Product_Name}.");
                }
            }

            $order = Order::create([
                'CustomerName' => $validated['CustomerName'],
                'Address' => $validated['Address'] ?? null,
                'ContactNumber' => $validated['ContactNumber'] ?? null,
                'OrderDate' => now(),
                'PaymentStatus' => $validated['PaymentStatus'],
                'Status' => $isPickup ? 'Completed' : 'Pending',
                'OrderType' => $validated['OrderType'],
                'Notes' => $validated['Notes'] ?? null,
                'CreatedBy' => auth()->id(),
            ]);

            $total = 0;
            foreach ($validated['items'] as $item) {
                $qtyValue = (float) $item['Quantity'];

                $order->orderItems()->create([
                    'ProductID' => $item['ProductID'],
                    'Quantity' => $item['Quantity'],
                    'Status' => $isPickup ? 'Fulfilled' : 'Pending',
                ]);

                // FIX: deduct stock for EVERY sale, not just Pickup, and
                // call the method that actually exists on the Inventory
                // model (deductQuantity, not deduct).
                if ($inventoryTrackingEnabled) {
                    Product::find($item['ProductID'])->inventory?->deductQuantity($qtyValue);
                }

                $total += $qtyValue * $item['UnitPrice'];
            }

            $transaction = Transaction::create([
                'OrderID' => $order->OrderID,
                'TransactionDate' => now(),
                'Amount' => $total,
                'PaymentMethod' => $validated['PaymentMethod'],
            ]);

            return $transaction->load('order.orderItems.product');
        });
    }
}