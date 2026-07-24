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
    public function delivery(){
        return $this->hasOne(Delivery::class, 'DispatchID', 'DispatchID');
    }
    public function truck()
    {
        return $this->belongsTo(Truck::class, 'TruckID', 'TruckID');
    }
    public function drivers(){
        return $this->belongsToMany(Driver::class, 'dispatch_drivers', 'DispatchID', 'DriverID')->withPivot('Role');
    }
    public function mainDriver(){
        return $this->belongsToMany(Driver::class, 'dispatch_drivers', 'DispatchID', 'DriverID')->wherePivot('Role', 'Main Driver');
    }
    public function scopeOnRoute($query){
        return $query->where('Status', 'On Route');
    }
    public function scopeCompleted($query){
        return $query->where('Status', 'Delivered');
    }
    public function mainDriverName(){
        return $this->mainDriver()->first()?->Name;
    }
}
