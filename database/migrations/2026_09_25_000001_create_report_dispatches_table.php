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
        Schema::create('report_dispatches', function (Blueprint $table) {
            $table->unsignedBigInteger('ReportID');
            $table->unsignedBigInteger('DispatchID');

            $table->primary(['ReportID', 'DispatchID']);

            $table->foreign('ReportID')->references('ReportID')->on('reports')->onDelete('cascade');
            $table->foreign('DispatchID')->references('DispatchID')->on('dispatches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_dispatches');
    }
};
