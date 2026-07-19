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
}
