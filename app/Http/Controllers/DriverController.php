<?php

namespace App\Http\Controllers;

use App\Models\DispatchDriver;
use App\Models\Driver;
use Illuminate\Http\Request;

class DriverController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Driver::query()->latest('DriverID')->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Create driver endpoint.'], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'Name' => 'required|string|max:255',
            'PhoneNumber' => 'nullable|string|max:20',
        ]);

        return Driver::create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Driver::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return Driver::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'Name' => 'sometimes|required|string|max:255',
            'PhoneNumber' => 'nullable|string|max:20',
        ]);

        $driver = Driver::findOrFail($id);
        $driver->fill($validated);
        $driver->save();

        return $driver;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $driver = Driver::findOrFail($id);
        $driver->delete();

        return response()->json(['message' => 'Driver deleted successfully.'], 200);
    }
    public function available(){
        $busyDriverIds = DispatchDriver::whereHas('dispatch', fn($q) => $q->where('Status', 'Dispatched'))
            ->pluck('DriverID');

        return Driver::whereNotIn('DriverID', $busyDriverIds)->get();
    }

    // Driver trip count / performance summary over a date range
    public function summary(Request $request, Driver $driver){
        $dispatches = $driver->dispatches()
            ->when($request->start, fn($q) => $q->whereDate('DispatchDate', '>=', $request->start))
            ->when($request->end, fn($q) => $q->whereDate('DispatchDate', '<=', $request->end))
            ->get();

        return [
            'total_trips' => $dispatches->count(),
            'as_main' => $dispatches->wherePivot('Role', 'Main')->count(),
            'as_assistant' => $dispatches->wherePivot('Role', 'Assistant')->count(),
        ];
    }
}
