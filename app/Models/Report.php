<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $primaryKey = 'ReportID';
    protected $fillable = [
        'ReportDate',
        'GeneratedAt',
        'TotalOrders',
        'TotalSales',
        'TotalItemsSold',
        'TotalDeliveries',
        'TotalDispatches',
        'Notes',
    ];
    protected $casts = [
        'ReportDate' => 'date', 
        'GeneratedAt' => 'datetime', 
        'TotalSales' => 'decimal:2'
    ];
    public $timestamps = false;
    public function scopeForDate($query, $date){
        return $query->whereDate('ReportDate', $date);
    }
    public function scopeThisMonth($query) {
        return $query->whereMonth('ReportDate', now()->month);
    }
}
