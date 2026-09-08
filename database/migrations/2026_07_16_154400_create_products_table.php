<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id('ProductID');
            $table->string('Product_Name');
            $table->string('Category');
            $table->string('Unit');
            $table->string('SubCategory');
            $table->string('SKU', 100)->nullable()->unique();
            // Price nullable to allow "Variable" products; store as decimal when available
            $table->decimal('Price', 12, 2)->nullable();
            // Pricing type: Fixed or Variable. Pricing status indicates whether a price has been resolved.
            $table->enum('Pricing_type', ['Fixed', 'Variable'])->default('Fixed');
            $table->enum('Pricing_status', ['Resolved', 'Unresolved'])->default('Resolved');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};