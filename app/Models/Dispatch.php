<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispatch extends Model
{
    protected $table = 'dispatches';
    protected $primaryKey = 'DispatchID';
    protected $fillable = [
        'OrderItemID',
        'TruckID',
        'DispatchDate',
        'QuantityDispatched',
        'Status',
    ];
    protected $guarded = ['DispatchID'];
    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class, 'OrderItemID', 'OrderItemID');
    }
    public function deliveries(){
        return $this->hasOne(Delivery::class, 'DispatchID', 'DispatchID');
    }
    public function truck()
    {
        return $this->belongsTo(Truck::class, 'TruckID', 'TruckID');
    }
    public function driver(){
        return $this->belongsToMany(Driver::class, 'dispatch_drivers', 'DispatchID', 'DriverID')->withPivot('Role');
    }

}
