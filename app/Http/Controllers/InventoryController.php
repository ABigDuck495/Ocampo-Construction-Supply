<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\Product;
use Illuminate\Http\Request;

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

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Create inventory entry endpoint.'], 200);
    }

    /**
     * Store a newly created resource in storage.
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
    public function checkAvailability(Request $request){
        $validated = $request->validate([
            'items' => 'required|array',
            'items.*.ProductID' => 'required|exists:products,ProductID',
            'items.*.Quantity' => 'required|integer|min:1',
        ]);

        $results = [];
        foreach ($validated['items'] as $item) {
            $onHand = Product::find($item['ProductID'])->inventory?->QuantityOnHand ?? 0;
            $results[] = [
                'ProductID' => $item['ProductID'],
                'requested' => $item['Quantity'],
                'available' => $onHand,
                'sufficient' => $onHand >= $item['Quantity'],
            ];
        }
        return $results;
    }
}
