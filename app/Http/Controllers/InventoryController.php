<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InventoryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inventory::query()
            ->with('product')
            ->latest('InventoryID')
            ->get();
    }


    public function page()
    {
        $inventories = Inventory::with('product')
            ->latest('InventoryID')
            ->get();

        $lowStockCount = Inventory::lowStock()->count();
        $outOfStockCount = Inventory::outOfStock()->count();

        return view('inventory.index', [
            'inventories'     => $inventories,
            'lowStockCount'   => $lowStockCount,
            'outOfStockCount' => $outOfStockCount,
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Create inventory entry endpoint.'], 200);
    }

    /**
     * Store a newly created resource in storage.
     * NOTE: this expects an EXISTING ProductID — use storeWithProduct()
     * below when the product itself doesn't exist yet (e.g. the "Add
     * Product" modal on the inventory page).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ProductID' => 'required|exists:products,ProductID',
            'QuantityOnHand' => 'required|integer|min:0',
            'ReorderLevel' => 'nullable|integer|min:0',
        ]);

        $inventory = Inventory::create([
            'ProductID' => $validated['ProductID'],
            'QuantityOnHand' => $validated['QuantityOnHand'],
            'ReorderLevel' => $validated['ReorderLevel'] ?? 10,
        ]);

        return $inventory->load('product');
    }

    /**
     * Create a brand-new Product AND its Inventory row together in one
     * request — used by the inventory page's "+ ADD PRODUCT" modal,
     * since that form collects both product details (name/SKU/category/
     * subcategory/price) and stock details (quantity/reorder level) at once.
     */
    public function storeWithProduct(Request $request)
    {
        $validated = $request->validate([
            'Product_Name'   => 'required|string|max:255',
            'Category'       => 'required|string|max:255',
            'SubCategory'    => 'required|string|max:255',
            'SKU'            => 'nullable|string|max:100|unique:products,SKU',
            'Price'          => 'required|numeric|min:0.01',
            'QuantityOnHand' => 'required|integer|min:0',
            'ReorderLevel'   => 'nullable|integer|min:0',
        ]);

        return DB::transaction(function () use ($validated) {
            $product = Product::create([
                'Product_Name' => $validated['Product_Name'],
                'Category'     => $validated['Category'],
                'SubCategory'  => $validated['SubCategory'],
                'SKU'          => $validated['SKU'] ?? null,
                'Price'        => $validated['Price'],
            ]);

            $inventory = Inventory::create([
                'ProductID'      => $product->ProductID,
                'QuantityOnHand' => $validated['QuantityOnHand'],
                'ReorderLevel'   => $validated['ReorderLevel'] ?? 10,
            ]);

            return $inventory->load('product');
        });
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Inventory::with('product')->findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return Inventory::with('product')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'ProductID' => 'sometimes|required|exists:products,ProductID',
            'QuantityOnHand' => 'sometimes|required|integer|min:0',
            'ReorderLevel' => 'sometimes|required|integer|min:0',
        ]);

        $inventory = Inventory::findOrFail($id);
        $inventory->fill($validated);
        $inventory->save();

        return $inventory->load('product');
    }

    /**
     * Update the Product's own fields (name/SKU/category/subcategory/price)
     * AND its Inventory row (quantity/reorder level) together — the
     * counterpart to storeWithProduct(), used by the inventory page's
     * EDIT button/modal.
     */
    public function updateWithProduct(Request $request, Inventory $inventory)
    {
        $validated = $request->validate([
            'Product_Name'   => 'required|string|max:255',
            'Category'       => 'required|string|max:255',
            'SubCategory'    => 'required|string|max:255',
            'SKU'            => 'nullable|string|max:100|unique:products,SKU,' . $inventory->ProductID . ',ProductID',
            'Price'          => 'required|numeric|min:0.01',
            'QuantityOnHand' => 'required|integer|min:0',
            'ReorderLevel'   => 'nullable|integer|min:0',
        ]);

        return DB::transaction(function () use ($validated, $inventory) {
            $inventory->product->update([
                'Product_Name' => $validated['Product_Name'],
                'Category'     => $validated['Category'],
                'SubCategory'  => $validated['SubCategory'],
                'SKU'          => $validated['SKU'] ?? null,
                'Price'        => $validated['Price'],
            ]);

            $inventory->update([
                'QuantityOnHand' => $validated['QuantityOnHand'],
                'ReorderLevel'   => $validated['ReorderLevel'] ?? $inventory->ReorderLevel,
            ]);

            return $inventory->fresh()->load('product');
        });
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $inventory = Inventory::findOrFail($id);
        $inventory->delete();

        return response()->json(['message' => 'Inventory entry deleted successfully.'], 200);
    }
    public function adjust(Request $request, Inventory $inventory){
        $validated = $request->validate([
            'Quantity' => 'required|integer',
            'Type' => 'required|in:Restock,Correction,Damage',
            'Notes' => 'nullable|string',
        ]);

        if ($validated['Type'] === 'Damage') {
            $inventory->deductQuantity(abs($validated['Quantity']));
        } elseif ($validated['Type'] === 'Restock') {
            $inventory->addQuantity(abs($validated['Quantity']));
        } else {
            // Correction: set directly to the counted value
            $inventory->update(['QuantityOnHand' => $validated['Quantity']]);
        }

        $inventory->touch('updated_at');

        return $inventory->fresh();
    }
    public function lowStock(){
        return Inventory::lowStock()->with('product')->get();
    }

    public function outOfStock(){
        return Inventory::outOfStock()->with('product')->get();
    }
    public function checkAvailability(Request $request)
    {
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.ProductID' => 'required|exists:products,ProductID',
            'items.*.Quantity' => 'required|string|max:50',
        ]);

        $results = [];
        foreach ($validated['items'] as $item) {
            $onHand = Product::find($item['ProductID'])->inventory?->QuantityOnHand ?? 0;
            $requestedValue = (float) $item['Quantity'];
            $results[] = [
                'ProductID' => $item['ProductID'],
                'requested' => $item['Quantity'],
                'available' => $onHand,
                'sufficient' => $onHand >= $requestedValue,
            ];
        }
        return $results;
    }
}