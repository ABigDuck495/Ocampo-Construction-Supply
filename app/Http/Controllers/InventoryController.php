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
