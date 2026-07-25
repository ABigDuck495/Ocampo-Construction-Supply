<?php

namespace App\Http\Controllers;

use App\Models\Dispatch;
use App\Models\DispatchDriver;
use App\Models\Driver;
use Illuminate\Http\Request;

class DispatchDriverController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return DispatchDriver::query()
            ->with('dispatch', 'driver')
            ->latest('DispatchDriverID')
            ->get();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Create dispatch driver assignment endpoint.'], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'DispatchID' => 'required|exists:dispatches,DispatchID',
            'DriverID' => 'required|exists:drivers,DriverID',
            'Role' => 'required|in:Main,Assistant',
        ]);

        $assignment = DispatchDriver::create($validated);

        return $assignment->load('dispatch', 'driver');
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return DispatchDriver::with('dispatch', 'driver')->findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return DispatchDriver::with('dispatch', 'driver')->findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'DispatchID' => 'sometimes|required|exists:dispatches,DispatchID',
            'DriverID' => 'sometimes|required|exists:drivers,DriverID',
            'Role' => 'sometimes|required|in:Main,Assistant',
        ]);

        $assignment = DispatchDriver::findOrFail($id);
        $assignment->fill($validated);
        $assignment->save();

        return $assignment->load('dispatch', 'driver');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $assignment = DispatchDriver::findOrFail($id);
        $assignment->delete();

        return response()->json(['message' => 'Dispatch driver assignment deleted successfully.'], 200);
    }
    public function assign(Request $request, Dispatch $dispatch){
        $validated = $request->validate([
            'DriverID' => 'required|exists:drivers,DriverID',
            'Role' => 'required|in:Main,Assistant',
        ]);

        if ($validated['Role'] === 'Main' && $dispatch->mainDriver()->exists()) {
            abort(422, 'This dispatch already has a main driver.');
        }

        if ($this->isDriverBusy($validated['DriverID'])) {
            abort(422, 'Driver is already assigned to an active dispatch.');
        }

        $dispatch->drivers()->attach($validated['DriverID'], ['Role' => $validated['Role']]);

        return $dispatch->load('drivers');
    }
    public function swap(Request $request, Dispatch $dispatch){
        $validated = $request->validate([
            'OldDriverID' => 'required|exists:drivers,DriverID',
            'NewDriverID' => 'required|exists:drivers,DriverID',
        ]);

        $pivot = $dispatch->drivers()->where('DriverID', $validated['OldDriverID'])->first()?->pivot;

        if (!$pivot) {
            abort(404, 'Driver not assigned to this dispatch.');
        }

        $dispatch->drivers()->detach($validated['OldDriverID']);
        $dispatch->drivers()->attach($validated['NewDriverID'], ['Role' => $pivot->Role]);

        return $dispatch->load('drivers');
    }
    private function isDriverBusy(int $driverId): bool{
        return DispatchDriver::where('DriverID', $driverId)
            ->whereHas('dispatch', fn($q) => $q->whereIn('Status', ['On Route', 'Delivered']))
            ->exists();
    }
    public function history(Driver $driver){
        return $driver->dispatches()->with('orderItem.order', 'truck')->latest('DispatchDate')->get();
    }
}
