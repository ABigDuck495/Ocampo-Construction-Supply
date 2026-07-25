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
            ->whereHas('dispatch', fn($q) => $q->where('Status', 'Dispatched'))
            ->exists();
    }
    public function history(Driver $driver){
        return $driver->dispatches()->with('orderItem.order', 'truck')->latest('DispatchDate')->get();
    }
}
