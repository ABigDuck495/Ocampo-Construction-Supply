<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DispatchLog extends Model
{
    protected $table = 'dispatch_logs';
    protected $primaryKey = 'DispatchLogID';
    public $timestamps = false;
    protected $fillable = ['DispatchID', 'Action', 'Notes', 'LoggedAt'];

    protected $casts = ['LoggedAt' => 'datetime'];

    public function dispatch()
    {
        return $this->belongsTo(Dispatch::class, 'DispatchID', 'DispatchID');
    }
}