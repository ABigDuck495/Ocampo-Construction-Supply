<?php

namespace App\Http\Controllers;

use App\Models\Truck;
use Illuminate\Http\Request;

class TruckController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Truck::query()->latest('TruckID')->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Create truck endpoint.'], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'TruckName' => 'nullable|string|max:255',
            'PlateNumber' => 'nullable|string|max:50',
            'Capacity' => 'nullable|numeric',
            'Status' => 'nullable|in:Available,Unavailable',
        ]);

        return Truck::create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Truck::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return Truck::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'TruckName' => 'sometimes|required|string|max:255',
            'PlateNumber' => 'nullable|string|max:50',
            'Capacity' => 'nullable|numeric',
            'Status' => 'nullable|in:Available,Unavailable',
        ]);

        $truck = Truck::findOrFail($id);
        $truck->fill($validated);
        $truck->save();

        return $truck;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $truck = Truck::findOrFail($id);
        $truck->delete();

        return response()->json(['message' => 'Truck deleted successfully.'], 200);
    }
    public function available(){
        return Truck::available()->get();
    }

    public function utilization(Request $request, Truck $truck){
        return $truck->dispatches()
            ->when($request->start, fn($q) => $q->whereDate('DispatchDate', '>=', $request->start))
            ->when($request->end, fn($q) => $q->whereDate('DispatchDate', '<=', $request->end))
            ->count();
    }
}
