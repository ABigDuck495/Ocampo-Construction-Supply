<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id('TransactionID');
            $table->unsignedBigInteger('OrderID');
            $table->timestamp('TransactionDate')->nullable();
            $table->decimal('Amount', 10, 2)->default(0);
            $table->enum('PaymentMethod', ['COD', 'GCash', 'Card', 'Bank Transfer'])->default('COD');
            $table->timestamps();
            $table->foreign('OrderID')->references('OrderID')->on('orders')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};