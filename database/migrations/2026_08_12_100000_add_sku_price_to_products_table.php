<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Category and SubCategory already exist (see
            // 2026_07_16_154400_create_products_table.php) — only SKU
            // and Price are actually missing.
            $table->string('SKU', 100)->nullable()->unique()->after('Product_Name');
            $table->decimal('Price', 10, 2)->default(0)->after('SubCategory');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['SKU', 'Price']);
        });
    }
};