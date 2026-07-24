<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchDriver extends Model
{
    protected $table = 'dispatch_drivers';
    protected $primaryKey = 'DispatchDriverID';
    protected $fillable = [
        'DispatchID',
        'DriverID',
        'Role',
    ];
    protected $guarded = ['DispatchDriverID'];
    public function dispatch(){
        return $this->belongsTo(Dispatch::class, 'DispatchID', 'DispatchID');
    }
    public function driver(){
        return $this->belongsTo(Driver::class, 'DriverID', 'DriverID');
    }
}
