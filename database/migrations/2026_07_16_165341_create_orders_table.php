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
        Schema::create('orders', function (Blueprint $table) {
            $table->id('OrderID');
            $table->string('CustomerName')->nullable();
            $table->string('Address')->nullable();
            $table->string('ContactNumber')->nullable();
            $table->timestamp('OrderDate')->nullable() ;
            $table->enum('PaymentStatus', ['Paid', 'Unpaid'])->default('Unpaid');
            $table->enum('Status', ['Pending', 'In Progress', 'Completed', 'Cancelled'])->default('Pending');
            $table->string('Notes')->nullable();
            $table->unsignedBigInteger(('CreatedBy'))->nullable();
            $table->foreign('CreatedBy')->references('UserID')->on('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
