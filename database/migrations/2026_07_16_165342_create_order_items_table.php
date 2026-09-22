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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id('OrderItemID');
            $table->unsignedBigInteger('OrderID');
            $table->unsignedBigInteger('ProductID');
            $table->decimal('Quantity', 10, 2)->nullable();
            // Per-line unit price when explicitly provided (nullable for variable pricing)
            $table->decimal('UnitPrice', 12, 2)->nullable();
            $table->enum('Pricing_method', ['Fixed', 'Variable'])->default('Fixed');
            $table->enum('Pricing_status', ['Resolved', 'Unresolved'])->default('Unresolved');
            $table->enum('Status', ['Pending', 'In Progress', 'Completed'])->default('Pending');
            $table->timestamps();
            $table->foreign('OrderID')->references('OrderID')->on('orders')->onDelete('cascade');
            $table->foreign('ProductID')->references('ProductID')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
