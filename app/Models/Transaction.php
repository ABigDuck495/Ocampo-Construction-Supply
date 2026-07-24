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
    ];
    protected $guarded = ['TransactionID'];
    public function order(){
        return $this->belongsTo(Order::class, 'OrderID', 'OrderID');
    }
    // public function products(){
    //     return $this->hasManyThrough(Product::class, OrderItem::class, 'OrderID', 'ProductID', 'OrderID', 'ProductID');
    // }
    // public function orderItems(){
    //     return $this->hasManyThrough(OrderItem::class, Order::class, 'OrderID', 'OrderID', 'OrderID', 'OrderID');
    // }
    // public function inventory(){
    //     return $this->hasManyThrough(Inventory::class, Product::class, 'ProductID', 'ProductID', 'ProductID', 'ProductID');
    // }
    public function scopeByMethod($query, $method) {
        return $query->where('Payment_method', $method);
    }
    public function scopeToday($query) {
        return $query->whereDate('TransactionDate', today());
    }
    public function totalForDate($date){
        return static::whereDate('TransactionDate', $date)->sum('TotalAmount');
    }
}
