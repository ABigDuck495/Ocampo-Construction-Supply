<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentStatusController extends Controller
{
    public function data()
    {
        return Order::with('transactions')
            ->latest('OrderID')
            ->limit(500)
            ->get()
            ->map(fn ($o) => [
                'OrderID'          => $o->OrderID,
                'CustomerName'     => $o->CustomerName,
                'OrderDate'        => $o->OrderDate,
                'PaymentStatus'    => $o->PaymentStatus,
                'PaymentMethod'    => $o->transactions?->PaymentMethod,
                'Amount'           => $o->transactions?->Amount,
                'ReceivedBy'       => $o->transactions?->ReceivedBy,
                'HandedOverBy'     => $o->transactions?->HandedOverBy,
                'PaymentUpdatedAt' => $o->transactions?->PaymentUpdatedAt,
            ]);
    }

    public function update(Request $request, string $id)
    {
        $validated = $request->validate([
            'PaymentMethod' => 'required|in:COD,GCash,Card,Bank Transfer',
            'PaymentStatus' => 'required|in:Paid,Payable,Unpaid',
            'ReceivedBy'    => 'required_if:PaymentStatus,Paid|nullable|string|max:255',
            'HandedOverBy'  => 'required_if:PaymentStatus,Paid|nullable|string|max:255',
        ], [
            'ReceivedBy.required_if'   => 'Enter the name of the person who received the payment.',
            'HandedOverBy.required_if' => 'Enter the name of the person who handed over the payment.',
        ]);

        $order = Order::with('transactions')->findOrFail($id);
        abort_if(! $order->transactions, 422, 'This order has no transaction record.');

        DB::transaction(function () use ($order, $validated) {
            $order->update(['PaymentStatus' => $validated['PaymentStatus']]);

            $order->transactions->update([
                'PaymentMethod'    => $validated['PaymentMethod'],
                'ReceivedBy'       => $validated['ReceivedBy'] ?? null,
                'HandedOverBy'     => $validated['HandedOverBy'] ?? null,
                'PaymentUpdatedAt' => now(),
            ]);
        });

        return response()->json(['message' => 'Payment updated.']);
    }
}