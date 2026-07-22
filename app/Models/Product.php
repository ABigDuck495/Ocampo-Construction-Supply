<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'ProductID';
    protected $fillable = [
        'ProductName'
    ];
    protected $guarded = ['ProductID'];
    public function inventory(){
        return $this->hasOne(Inventory::class, 'ProductID', 'ProductID');
    }
    public function orderItems(){
        return $this->hasMany(OrderItem::class, 'ProductID', 'ProductID');
    }
    public function transactions(){
        return $this->hasManyThrough(Transaction::class, OrderItem::class, 'ProductID', 'OrderID', 'ProductID', 'OrderID');
    }

}
