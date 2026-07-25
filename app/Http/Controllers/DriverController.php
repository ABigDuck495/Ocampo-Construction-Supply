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
