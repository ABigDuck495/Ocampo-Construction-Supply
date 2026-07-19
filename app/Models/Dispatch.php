<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dispatch extends Model
{
    protected $table = 'dispatches';
    protected $primaryKey = 'DispatchID';
    protected $fillable = [
        'OrderItemID',
        'DriverID',
        'TruckID',
        'DispatchDate',
        'QuantityDispatched',
        'Status',
    ];
}
