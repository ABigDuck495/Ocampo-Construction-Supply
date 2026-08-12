<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The original migration locked PaymentMethod to
     * ['Cash', 'Credit', 'Cash On Delivery'], but the POS UI's payment
     * buttons actually send 'COD', 'GCash', 'Card', 'Bank Transfer' —
     * none of which matched, causing "Data truncated for column
     * 'PaymentMethod'" on every checkout. This swaps the enum to the
     * values the app actually uses.
     */
    public function up(): void
    {
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
            MODIFY PaymentMethod ENUM('Cash', 'Credit', 'Cash On Delivery')
            NOT NULL DEFAULT 'Cash On Delivery'
        ");
    }
};