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
        Schema::create('dispatches', function (Blueprint $table) {
            $table->id('DispatchID');
            $table->unsignedBigInteger(('OrderItemID'));
            $table->unsignedBigInteger(('DriverID'))->nullable();
            $table->unsignedBigInteger(('TruckID'))->nullable();
            $table->timestamp('DispatchDate')->nullable();
            $table->integer('QuantityDispatched')->default(1);
            $table->enum('Status', ['Pending', 'On Route', 'Delivered'])->default('Pending');
            $table->timestamps();
            $table->foreign('OrderItemID')->references('OrderItemID')->on('order_items')->onDelete('cascade');
            $table->foreign('DriverID')->references('DriverID')->on('drivers')->onDelete('set null');
            $table->foreign('TruckID')->references('TruckID')->on('trucks')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch');
    }
};
