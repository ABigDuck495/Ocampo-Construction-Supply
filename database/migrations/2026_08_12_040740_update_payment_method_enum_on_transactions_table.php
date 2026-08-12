<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The straight MODIFY to the new enum failed because existing rows
     * still hold the old values ('Cash', 'Credit', 'Cash On Delivery'),
     * which aren't valid members of the new enum — MySQL has to
     * re-validate every row against the new list during the ALTER, and
     * those rows don't fit, so it throws the same truncation error
     * instead of an insert-time one.
     *
     * Fix: widen the enum to allow BOTH old and new values first, remap
     * existing data to the new values, then narrow the enum down.
     */
    public function up(): void
    {
        // Step 1: widen so old data is still valid while we remap it
        DB::statement("
            ALTER TABLE transactions
            MODIFY PaymentMethod ENUM('Cash', 'Credit', 'Cash On Delivery', 'COD', 'GCash', 'Card', 'Bank Transfer')
            NOT NULL DEFAULT 'COD'
        ");

        // Step 2: remap existing rows to the new value set
        DB::table('transactions')->where('PaymentMethod', 'Cash')->update(['PaymentMethod' => 'COD']);
        DB::table('transactions')->where('PaymentMethod', 'Cash On Delivery')->update(['PaymentMethod' => 'COD']);
        DB::table('transactions')->where('PaymentMethod', 'Credit')->update(['PaymentMethod' => 'Card']);

        // Step 3: narrow the enum down to only the new values
        DB::statement("
            ALTER TABLE transactions
            MODIFY PaymentMethod ENUM('COD', 'GCash', 'Card', 'Bank Transfer')
            NOT NULL DEFAULT 'COD'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("
            ALTER TABLE transactions
            MODIFY PaymentMethod ENUM('Cash', 'Credit', 'Cash On Delivery', 'COD', 'GCash', 'Card', 'Bank Transfer')
            NOT NULL DEFAULT 'Cash On Delivery'
        ");

        DB::table('transactions')->where('PaymentMethod', 'COD')->update(['PaymentMethod' => 'Cash On Delivery']);
        DB::table('transactions')->where('PaymentMethod', 'Card')->update(['PaymentMethod' => 'Credit']);
        DB::table('transactions')->where('PaymentMethod', 'GCash')->update(['PaymentMethod' => 'Cash On Delivery']);
        DB::table('transactions')->where('PaymentMethod', 'Bank Transfer')->update(['PaymentMethod' => 'Cash On Delivery']);

        DB::statement("
            ALTER TABLE transactions
            MODIFY PaymentMethod ENUM('Cash', 'Credit', 'Cash On Delivery')
            NOT NULL DEFAULT 'Cash On Delivery'
        ");
    }
};