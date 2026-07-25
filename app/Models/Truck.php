<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Truck extends Model
{
    protected $table = 'trucks';
    protected $primaryKey = 'TruckID';
    protected $fillable = [
        'TruckName',
        'PlateNumber',
        'Capacity',
        'Status',
    ];
    protected $guarded = ['TruckID'];
    public function dispatches(){
        return $this->hasMany(Dispatch::class, 'TruckID', 'TruckID');
    }
    // public function drivers(){
    //     return $this->belongsToMany(Driver::class, 'dispatch_drivers', 'TruckID', 'DriverID')->withPivot('Role');
    // }
    public function scopeAvailable($query){
        return $query->where('Status', 'Available');
    }
    public function isAvailable(){
        return $this->Status === 'Available';
    }

}
