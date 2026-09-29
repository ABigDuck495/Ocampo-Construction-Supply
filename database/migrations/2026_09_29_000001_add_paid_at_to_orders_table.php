<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('PaidAt')->nullable();
        });

        DB::table('orders')
            ->where('PaymentStatus', 'Paid')
            ->update([
                'PaidAt' => DB::raw(
                    'COALESCE((SELECT TransactionDate FROM transactions WHERE transactions.OrderID = orders.OrderID LIMIT 1), OrderDate)'
                ),
            ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('PaidAt');
        });
    }
};