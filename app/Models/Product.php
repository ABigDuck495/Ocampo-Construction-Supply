<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'ProductID';
    protected $fillable = [
        'Product_Name',
        'ProductName'
    ];
    protected $guarded = ['ProductID'];
    public function inventory(){
        return $this->hasOne(Inventory::class, 'ProductID', 'ProductID');
    }
    public function orderItems(){
        return $this->hasMany(OrderItem::class, 'ProductID', 'ProductID');
    }
    // public function transactions(){
    //     return $this->hasManyThrough(Transaction::class, OrderItem::class, 'ProductID', 'OrderID', 'ProductID', 'OrderID');
    // }
    // public function scopeActive($query) {
    //     return $query->where('Status', 'Active');
    // }
    // public function scopeInactive($query) {
    //     return $query->where('Status', 'Inactive');
    // }
    public function isLowStock() {
        return $this->inventory && $this->inventory->QuantityOnHand <= $this->inventory->ReorderLevel;
    }
    public function currentStock() {
        return $this->inventory ? $this->inventory->QuantityOnHand : 0;
    }
    public function reorderLevel() {
        return $this->inventory ? $this->inventory->ReorderLevel : 0;
    }
    public function isOutOfStock() {
        return $this->inventory && $this->inventory->QuantityOnHand <= 0;
    }

}
