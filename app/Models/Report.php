<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $table = 'reports';
    protected $primaryKey = 'ReportID';
    protected $fillable = [
        'ReportDate',
        'GeneratedAt',
        'TotalOrders',
        'TotalSales',
        'TotalItemsSold',
        'TotalDeliveries',
        'TotalDeliveriesFailed',
        'TotalDispatches',
        'LowStockItemCount',
        'Notes',
    ];
    protected $casts = [
        'ReportDate' => 'date',
        'GeneratedAt' => 'datetime',
        'TotalSales' => 'decimal:2',
        'TotalOrders' => 'integer',
        'TotalItemsSold' => 'integer',
        'TotalDeliveries' => 'integer',
        'TotalDeliveriesFailed' => 'integer',
        'LowStockItemCount' => 'integer',
    ];
    public $timestamps = false;

    public function dispatches()
    {
        return $this->belongsToMany(Dispatch::class, 'report_dispatches', 'ReportID', 'DispatchID');
    }

    public function deliveries()
    {
        return $this->belongsToMany(Delivery::class, 'report_deliveries', 'ReportID', 'DeliveryID');
    }

    public function scopeForDate($query, $date)
    {
        return $query->whereDate('ReportDate', $date);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('ReportDate', now()->month);
    }
}
