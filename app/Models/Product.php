<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $table = 'products';
    protected $primaryKey = 'ProductID';

    // Matches the real columns: ProductID, Category, SubCategory,
    // Product_Name (all NOT NULL from the original migration), plus
    // SKU and Price (nullable/defaulted, added separately).
    protected $fillable = [
        'Product_Name',
        'SKU',
        'Unit',
        'Category',
        'SubCategory',
        'Price',
        'Pricing_type',
        'Pricing_status',
    ];

    public function inventory(){
        return $this->hasOne(Inventory::class, 'ProductID', 'ProductID');
    }
    public function orderItems(){
        return $this->hasMany(OrderItem::class, 'ProductID', 'ProductID');
    }
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

    // Returns numeric unit price when available, or null for variable pricing
    public function getUnitPriceAttribute()
    {
        if (isset($this->attributes['Price']) && is_numeric($this->attributes['Price'])) {
            return (float) $this->attributes['Price'];
        }
        return null;
    }

    public function isVariablePricing(): bool
    {
        return isset($this->attributes['Pricing_type']) && $this->attributes['Pricing_type'] === 'Variable';
    }
}