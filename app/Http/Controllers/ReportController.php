<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateDailyReportJob;
use App\Models\Report;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('reports.index', [
            'reports' => Report::latest('ReportDate')->paginate(20),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json(['message' => 'Create report endpoint.'], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'ReportDate' => 'required|date',
            'GeneratedAt' => 'nullable|date',
            'TotalOrders' => 'nullable|integer',
            'TotalSales' => 'nullable|numeric',
            'TotalItemsSold' => 'nullable|integer',
            'TotalDeliveries' => 'nullable|integer',
            'TotalDispatches' => 'nullable|integer',
            'Notes' => 'nullable|string',
        ]);

        return Report::create($validated);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return Report::findOrFail($id);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return Report::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'ReportDate' => 'sometimes|required|date',
            'GeneratedAt' => 'nullable|date',
            'TotalOrders' => 'nullable|integer',
            'TotalSales' => 'nullable|numeric',
            'TotalItemsSold' => 'nullable|integer',
            'TotalDeliveries' => 'nullable|integer',
            'TotalDispatches' => 'nullable|integer',
            'Notes' => 'nullable|string',
        ]);

        $report = Report::findOrFail($id);
        $report->fill($validated);
        $report->save();

        return $report;
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $report = Report::findOrFail($id);
        $report->delete();

        return response()->json(['message' => 'Report deleted successfully.'], 200);
    }
    public function generateNow(){
        return app(GenerateDailyReportJob::class)->handle();
    }

    public function forDate(Request $request){
        return Report::forDate($request->date ?? today())->firstOrFail();
    }

    public function trend(Request $request){
        return Report::query()
            ->whereBetween('ReportDate', [$request->start, $request->end])
            ->orderBy('ReportDate')
            ->get(['ReportDate', 'TotalSales', 'TotalOrders', 'TotalDeliveries']);
    }
}
