<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';
    protected $primaryKey = 'OrderID';
    protected $fillable = [
       'CustomerName',
        'Address',
        'PhoneNumber',
        'OrderDate',
        'PaymentStatus',
        'Status',
        'Notes',
        'CreatedBy',
    ];
    protected $guarded = ['OrderID'];
    public function orderItems(){
        return $this->hasMany(OrderItem::class, 'OrderID', 'OrderID');
    }
    public function transactions() {
        return $this->hasOne(Transaction::class, 'OrderID', 'OrderID');
    }
}
