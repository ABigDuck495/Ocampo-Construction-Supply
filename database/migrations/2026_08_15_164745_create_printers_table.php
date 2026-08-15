<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('connection_type', ['network', 'usb', 'bluetooth']);
            $table->string('ip_address')->nullable();
            $table->integer('port')->nullable();
            $table->string('usb_printer_name')->nullable();
            $table->string('bluetooth_service_uuid')->nullable();
            $table->string('bluetooth_characteristic_uuid')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printers');
    }
};