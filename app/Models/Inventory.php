<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inventory extends Model
{
    protected $table = 'inventories';
    protected $primaryKey = 'InventoryID';
    protected $fillable = [
        'ProductID',
        'QuantityOnHand',
        'ReorderLevel',
    ];
    protected $guarded = ['InventoryID'];
    public function product()
    {
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID');
    }
}
