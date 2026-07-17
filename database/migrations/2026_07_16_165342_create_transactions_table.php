<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id('TransactionID');
            $table->unsignedBigInteger('OrderID');
            $table->timestamp('TransactionDate')->nullable();
            $table->float('Amount', precision: 53)->default(0);
            $table->enum('PaymentMethod', ['Cash', 'Credit', 'Cash On Delivery'])->default('Cash On Delivery');
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
