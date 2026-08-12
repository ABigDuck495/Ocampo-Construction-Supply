<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Product::query()
            ->with('inventory')
            ->latest('ProductID')
            ->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Create product endpoint.'], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Category and SubCategory are NOT NULL with no default on the
        // products table, so both are required here.
        $validated = $request->validate([
        'Product_Name'   => 'required|string|max:255',
        'Category'       => 'required|string|max:255',
        'SubCategory'    => 'required|string|max:255',
        'SKU'            => 'nullable|string|max:100|unique:products,SKU',
        'Price'          => 'required|numeric|min:0.01',
        'QuantityOnHand' => 'required|integer|min:0',
        'ReorderLevel'   => 'nullable|integer|min:0',
]);

        $product = Product::create($validated);

        return $product->load('inventory');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Product::with('inventory')->findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return Product::with('inventory')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'Product_Name' => 'sometimes|required|string|max:255',
            'Category'     => 'sometimes|required|string|max:255',
            'SubCategory'  => 'sometimes|required|string|max:255',
            'SKU'          => 'sometimes|nullable|string|max:100|unique:products,SKU,' . $id . ',ProductID',
            'Price'        => 'sometimes|nullable|numeric|min:0',
        ]);

        $product = Product::findOrFail($id);
        $product->fill($validated);
        $product->save();

        return $product->load('inventory');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.'], 200);
    }

    public function search(Request $request){
        return Product::where('Product_Name', 'like', '%' . $request->q . '%')
            ->with('inventory')
            ->limit(20)
            ->get();
    }

    public function topSelling(Request $request){
        return OrderItem::query()
            ->whereHas('order', fn($q) => $q
                ->when($request->start, fn($q2) => $q2->whereDate('OrderDate', '>=', $request->start))
                ->when($request->end, fn($q2) => $q2->whereDate('OrderDate', '<=', $request->end))
            )
            ->selectRaw('ProductID, SUM(CAST(Quantity AS DECIMAL(10,2))) as total_sold')
            ->groupBy('ProductID')
            ->orderByDesc('total_sold')
            ->with('product')
            ->limit(10)
            ->get();
    }
}