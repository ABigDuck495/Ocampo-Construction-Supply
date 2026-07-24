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
        Schema::create('reports', function (Blueprint $table) {
            $table->id('ReportID');
            $table->date('ReportDate');
            $table->timestamp('GeneratedAt');
            $table->integer('TotalOrders');            
            $table->integer('TotalSales');            
            $table->integer('TotalItemsSold');            
            $table->integer('TotalDeliveries');            
            $table->integer('TotalDispatches');            
            $table->string('Notes');            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
