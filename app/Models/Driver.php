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
}
