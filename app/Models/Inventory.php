<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventory';
    protected $primaryKey = 'InventoryID';
    protected $fillable = [
        'ProductID',
        'QuantityOnHand',
        'ReorderLevel',
    ];
    protected $guarded = ['InventoryID'];
    public function product(){
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }
    public function scopeLowStock($query){
        return $query->whereColumn('QuantityOnHand', '<=', 'ReorderLevel');
    }
    public function scopeOutOfStock($query){
        return $query->where('QuantityOnHand', '<=', 0);
    }
    public function deductQuantity($quantity){
        if($this->QuantityOnHand >= $quantity){
            $this->QuantityOnHand -= $quantity;
            $this->save();
        } else {
            throw new \Exception('Insufficient stock for product ID: ' . $this->ProductID);
        }
    }
    public function addQuantity($quantity){
        $this->QuantityOnHand += $quantity;
        $this->save();
    }
    public function isLowStock() {
        return $this->QuantityOnHand <= $this->ReorderLevel;
    }
    public function isOutOfStock() {
        return $this->QuantityOnHand <= 0;
    }
    public function currentStock() {
        return $this->QuantityOnHand;
    }
    
}
