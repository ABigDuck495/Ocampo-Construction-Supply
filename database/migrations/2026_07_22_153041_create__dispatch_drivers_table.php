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
        Schema::create('dispatch_drivers', function (Blueprint $table) {
            $table->id('DispatchDriverID');
            $table->unsignedBigInteger('DispatchID');
            $table->unsignedBigInteger('DriverID');
            $table->enum('Role', ['Driver', 'Helper'])->default('Driver');
            $table->foreign('DispatchID')->references('DispatchID')->on('dispatch')->onDelete('cascade');
            $table->foreign('DriverID')->references('DriverID')->on('drivers')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('_dispatch_drivers');
    }
};
