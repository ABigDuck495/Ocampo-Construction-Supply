<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $table = 'drivers';
    protected $primaryKey = 'DriverID';
    protected $fillable = [
        'Name',
        'PhoneNumber',
    ];
    protected $guarded = ['DriverID'];
    
    public function dispatches(){
    return $this->belongsToMany(Dispatch::class, 'dispatch_drivers', 'DriverID', 'DispatchID')
                ->withPivot('Role')
                ->withTimestamps();
    }
    public function deliveries()
    {
        return $this->hasManyThrough(Delivery::class, Dispatch::class, 'DispatchID', 'DispatchID', 'DriverID', 'DispatchID');
    }
    public function getDeliveriesCountAttribute()
    {
        return $this->deliveries()->count();
    }

}
