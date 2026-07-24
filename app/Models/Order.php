<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $table = 'orders';
    protected $primaryKey = 'OrderID';
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
    public function scopeUnpaid($query) {
        return $query->where('PaymentStatus', 'Unpaid');
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
        return $this->transactions ? $this->transactions->Amount : 0;
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
}
