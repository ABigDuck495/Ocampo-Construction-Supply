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
        Schema::create('System_Settings', function (Blueprint $table) {
            $table->id('Setting_ID');
            $table->string('Setting_Key', 255)->unique();
            $table->text('Setting_Value')->nullable();
            $table->enum('Setting_Group', ['General', 'Inventory', 'Logistics', 'POS', 'Printer'])->default('General');
            $table->string('Setting_Description', 255)->nullable();    
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('System_Settings');
    }
};
