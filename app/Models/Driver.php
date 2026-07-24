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
    
    public function dispatches() {
        return $this->belongsToMany(Dispatch::class, 'dispatch_drivers', 'DriverID', 'DispatchID')
                    ->withPivot('Role');
    }
    public function dispatchDrivers(){
        return $this->hasMany(DispatchDriver::class, 'DriverID', 'DriverID');
    }
    public function activeDispatches(){
        return $this->dispacthes()->wherePivot('Role', 'Main')
                    ->where('Status', 'On Route');
    }
    public function getDeliveriesCountAttribute(){
        return $this->deliveries()->count();
    }

}
