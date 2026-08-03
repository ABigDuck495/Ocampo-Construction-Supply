<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    public const STATUS_PENDING = 'Pending';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_COMPLETED = 'Completed';

    protected $table = 'order_items';
    protected $primaryKey = 'OrderItemID';
    protected $fillable = [
        'OrderID',
        'ProductID',
        'Quantity',
        'Status',
    ];
    protected $guarded = ['OrderItemID'];
    protected $casts = [
        'OrderID' => 'integer',
        'ProductID' => 'integer',
        'Quantity' => 'integer',
    ];

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
    public static function allowedStatuses(): array
    {
        return [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_COMPLETED];
    }

    public static function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'partially fulfilled', 'in progress', 'in-progress' => self::STATUS_IN_PROGRESS,
            'fulfilled', 'completed' => self::STATUS_COMPLETED,
            default => self::STATUS_PENDING,
        };
    }

    public function setStatusAttribute($value): void
    {
        $this->attributes['Status'] = self::normalizeStatus((string) $value);
    }

    public function scopePending($query){
        return $query->where('Status', self::STATUS_PENDING);
    }

    public function scopeInProgress($query){
        return $query->where('Status', self::STATUS_IN_PROGRESS);
    }

    public function scopeCompleted($query){
        return $query->where('Status', self::STATUS_COMPLETED);
    }
    public function subtotal() {
        return (float) $this->Quantity * $this->product->UnitPrice;
    }
    public function quantityDispatched() {
        return $this->relationLoaded('dispatches')
            ? $this->dispatches->sum('QuantityDispatched')
            : $this->dispatches()->sum('QuantityDispatched');
    }
    public function quantityRemaining() {
        return (float) $this->Quantity - $this->quantityDispatched();
    }
}
