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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id('DeliveryID');
            $table->unsignedBigInteger('DispatchID');
            $table->timestamp('DeliveryDate')->nullable();
            $table->integer('QuantityDelivered')->default(1);
            $table->enum('Status', ['Cancelled', 'Delivered'])->default('Delivered');
            $table->string('Notes')->nullable();
            $table->foreign('DispatchID')->references('DispatchID')->on('dispatch')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
