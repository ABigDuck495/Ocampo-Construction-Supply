<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';
    protected $primaryKey = 'OrderID';

    public const PAYMENT_STATUS_PAYABLE = 'Payable';
    public const PAYMENT_STATUS_PAID = 'Paid';

    protected $fillable = [
       'CustomerName',
        'Address',
        'ContactNumber',
        'OrderDate',
        'PaymentStatus',
        'Status',
        'Notes',
        'CreatedBy',
    ];
    protected $guarded = ['OrderID'];
    protected $casts = [
        'PaidAt' => 'datetime',
    ];

    public static function normalizePaymentStatus($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return match ($normalized) {
            'Unpaid', 'Payable' => self::PAYMENT_STATUS_PAYABLE,
            'Paid' => self::PAYMENT_STATUS_PAID,
            default => $normalized,
        };
    }

    public function getPaymentStatusAttribute($value)
    {
        return self::normalizePaymentStatus($value);
    }

    public function setPaymentStatusAttribute($value)
    {
        $status = self::normalizePaymentStatus($value);
        $this->attributes['PaymentStatus'] = $status;

        if ($status === self::PAYMENT_STATUS_PAID && $this->getRawOriginal('PaymentStatus') !== self::PAYMENT_STATUS_PAID) {
            $this->attributes['PaidAt'] = now();
        } elseif ($status === self::PAYMENT_STATUS_PAYABLE && $this->getRawOriginal('PaymentStatus') === self::PAYMENT_STATUS_PAID) {
            $this->attributes['PaidAt'] = null;
        }
    }

    public function orderItems(){
        return $this->hasMany(OrderItem::class, 'OrderID', 'OrderID');
    }
    public function transactions() {
        return $this->hasOne(Transaction::class, 'OrderID', 'OrderID');
    }
    public function user(){
        return $this->belongsTo(User::class, 'CreatedBy', 'UserID');
    }
    public function scopePending($query) {
        return $query->where('Status', 'Pending');
    }
    public function scopeCompleted($query) {
        return $query->where('Status', 'Completed');
    }
    public function scopeCancelled($query) {
        return $query->where('Status', 'Cancelled');
    }
    public function scopeInProgress($query) {
        return $query->where('Status', 'In Progress');
    }
    public function scopePaid($query) {
        return $query->where('PaymentStatus', 'Paid');
    }
    public function scopePayable($query) {
        return $query->whereIn('PaymentStatus', ['Payable', 'Unpaid']);
    }
    public function scopeUnpaid($query) {
        return $query->whereIn('PaymentStatus', ['Payable', 'Unpaid']);
    }
    public function scopeToday($query) {
        return $query->whereDate('OrderDate', now()->toDateString());
    }
    public function scopeThisWeek($query) {
        return $query->whereBetween('OrderDate', [now()->startOfWeek(), now()->endOfWeek()]);
    }
    public function scopeThisMonth($query) {
        return $query->whereMonth('OrderDate', now()->month);
    }
    public function scopeBetweenDates($query, $startDate, $endDate) {
        return $query->whereBetween('OrderDate', [$startDate, $endDate]);
    }
    public function totalAmount() {
        if ($this->transactions) {
            return (float) $this->transactions->Amount;
        }

        return $this->orderItems->sum(fn ($item) => $item->subtotal());
    }
    public function isFullyDelivered() {
        return $this->orderItems->every(function ($item) {
            $deliveredQty = $item->dispatches->sum(function ($dispatch) {
                return $dispatch->delivery && $dispatch->delivery->Status === 'Delivered'
                    ? $dispatch->delivery->QuantityDelivered
                    : 0;
            });
            return $deliveredQty >= $item->Quantity;
        });
    }

    public function markPaymentStatusByDeliveryState(): void
    {
        $newStatus = $this->isFullyDelivered() ? self::PAYMENT_STATUS_PAID : self::PAYMENT_STATUS_PAYABLE;

        if ($this->PaymentStatus !== $newStatus) {
            $this->update(['PaymentStatus' => $newStatus]);
        }
    }
}
