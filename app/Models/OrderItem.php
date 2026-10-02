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
        'UnitPrice',
        'Pricing_method',
        'Pricing_status',
    ];
    protected $guarded = ['OrderItemID'];
    protected $casts = [
        'OrderID' => 'integer',
        'ProductID' => 'integer',
        'Quantity' => 'float',
        'UnitPrice' => 'float',
    ];

    public function order(){
        return $this->belongsTo(Order::class, 'OrderID', 'OrderID');
    }
    public function product(){
        return $this->belongsTo(Product::class, 'ProductID', 'ProductID')->withTrashed();
    }

    public function dispatches(){
        return $this->hasMany(Dispatch::class, 'OrderItemID', 'OrderItemID');
    }

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

    public function scopeAwaitingDispatch($query)
    {
        // A 'Partial' dispatch only claims what was actually delivered; the
        // shortfall goes back into the pool so it can be dispatched again.
        return $query->whereRaw(
            'CAST(Quantity AS DECIMAL(10,2)) > (SELECT COALESCE(SUM(CASE WHEN dispatches.Status = ? THEN COALESCE((SELECT SUM(deliveries.QuantityDelivered) FROM deliveries WHERE deliveries.DispatchID = dispatches.DispatchID), 0) ELSE dispatches.QuantityDispatched END), 0) FROM dispatches WHERE dispatches.OrderItemID = order_items.OrderItemID AND dispatches.Status != ?)',
            ['Partial', 'Failed']
        );
    }

    public static function awaitingDispatchOrderCount(): int
    {
        return static::query()
            ->awaitingDispatch()
            ->distinct()
            ->count('OrderID');
    }

    public function scopeInProgress($query){
        return $query->where('Status', self::STATUS_IN_PROGRESS);
    }

    public function scopeCompleted($query){
        return $query->where('Status', self::STATUS_COMPLETED);
    }

    public function subtotal() {
        $unit = $this->UnitPrice ?? $this->product?->UnitPrice;
        return (float) $this->Quantity * (float) ($unit ?? 0);
    }

    /**
     * Sum of quantity across dispatches still "claiming" stock — everything
     * except Failed/cancelled ones, since those release their quantity back
     * into the pending pool. A 'Partial' dispatch only claims the quantity
     * that was actually delivered; the shortfall is released back too.
     */
    public function quantityDispatched() {
        $dispatches = $this->relationLoaded('dispatches')
            ? $this->dispatches
            : $this->dispatches()->with('delivery')->get();

        return $dispatches
            ->where('Status', '!=', 'Failed')
            ->sum(function ($dispatch) {
                if ($dispatch->Status === 'Partial') {
                    return (float) ($dispatch->delivery?->QuantityDelivered ?? 0);
                }

                return (float) $dispatch->QuantityDispatched;
            });
    }

    public function quantityRemaining() {
        return (float) $this->Quantity - $this->quantityDispatched();
    }

    /**
     * Accessors so these values can be included when the model is turned
     * into an array/JSON (for example through $appends or ->append()).
     * They just call the existing methods above.
     */
    public function getQuantityRemainingAttribute()
    {
        return $this->quantityRemaining();
    }

    public function getQuantityDispatchedAttribute()
    {
        return $this->quantityDispatched();
    }

    public function recalculateStatus(): void
    {
        $quantity = (float) ($this->Quantity ?? 0);
        $dispatched = (float) $this->quantityDispatched();

        if ($quantity <= 0 || $dispatched <= 0) {
            $status = self::STATUS_PENDING;
        } elseif ($dispatched >= $quantity) {
            $status = self::STATUS_COMPLETED;
        } else {
            $status = self::STATUS_IN_PROGRESS;
        }

        if ($this->Status !== $status) {
            $this->update(['Status' => $status]);
        }
    }
}