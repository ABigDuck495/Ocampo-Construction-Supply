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
    public function search(Request $request){
        return Product::where('Product_Name', 'like', '%' . $request->q . '%')
            ->with('inventory')
            ->limit(20)
            ->get();
    }

    public function topSelling(Request $request){
        return OrderItem::query()
            ->whereHas('order', fn($q) => $q
                ->when($request->start, fn($q2) => $q2->whereDate('Order_Date', '>=', $request->start))
                ->when($request->end, fn($q2) => $q2->whereDate('Order_Date', '<=', $request->end))
            )
            ->selectRaw('ProductID, SUM(Quantity) as total_sold')
            ->groupBy('ProductID')
            ->orderByDesc('total_sold')
            ->with('product')
            ->limit(10)
            ->get();
    }
}
