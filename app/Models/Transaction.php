<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $table = 'transactions';
    protected $primaryKey = 'TransactionID';
    protected $fillable = [
        'OrderID',
        'TransactionDate',
        'Amount',
        'PaymentMethod',
        'TotalPrice',
    ];
}
