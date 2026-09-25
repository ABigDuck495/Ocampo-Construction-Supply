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
        Schema::create('report_deliveries', function (Blueprint $table) {
            $table->unsignedBigInteger('ReportID');
            $table->unsignedBigInteger('DeliveryID');

            $table->primary(['ReportID', 'DeliveryID']);

            $table->foreign('ReportID')->references('ReportID')->on('reports')->onDelete('cascade');
            $table->foreign('DeliveryID')->references('DeliveryID')->on('deliveries')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_deliveries');
    }
};
