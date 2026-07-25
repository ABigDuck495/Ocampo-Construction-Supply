<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $table = 'order_items';
    protected $primaryKey = 'OrderItemID';
    protected $fillable = [
        'OrderID',
        'ProductID',
        'Quantity',
        'Status',
    ];
    protected $guarded = ['OrderItemID'];

    public function order(){
        return $this->belongsTo(Order::class, 'OrderID', 'OrderID');
    }
    public function product(){
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }

    public function dispatches(){
        return $this->hasMany(Dispatch::class, 'OrderItemID', 'OrderItemID');
    }
    // public function dispatches(){
    //     return $this->hasMany(Dispatch::class, 'OrderItemID', 'OrderItemID');
    // }
    // public function deliveries(){
    //     return $this->hasOneThrough(Delivery::class, Dispatch::class, 'OrderItemID', 'DispatchID', 'OrderItemID', 'DispatchID');
    // }
    // public function inventory(){
    //     return $this->hasOne(Inventory::class, 'ProductID', 'ProductID');
    // }
    public function scopePending($query){
        return $query->where('Status', 'Pending');
    }
    public function scopeCompleted($query){
        return $query->where('Status', 'Completed');
    }
    public function subtotal(){
        return $this->Quantity * $this->product->UnitPrice;
    }
    public function quantityDispatched() {
        return $this->relationLoaded('dispatches')
            ? $this->dispatches->sum('QuantityDispatched')
            : $this->dispatches()->sum('QuantityDispatched');
    }
    public function quantityRemaining() {
        return $this->Quantity - $this->quantityDispatched();
    }
}
