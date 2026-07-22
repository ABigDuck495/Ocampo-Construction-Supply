<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $table = 'deliveries';
    protected $primaryKey = 'DeliveryID';
    protected $fillable = [
        'DispatchID',
        'DeliveryDate',
        'QuantityDelivered',
        'Status',
        'Notes',
    ];
    protected $guarded = ['DeliveryID'];
    public function dispatch()
    {
        return $this->belongsTo(Dispatch::class, 'DispatchID', 'DispatchID');
    }
}
