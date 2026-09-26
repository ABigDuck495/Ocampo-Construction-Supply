<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE orders MODIFY Status ENUM('Pending', 'In Progress', 'Partially Fulfilled', 'Completed', 'Cancelled') DEFAULT 'Pending'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE orders MODIFY Status ENUM('Pending', 'In Progress', 'Completed', 'Cancelled') DEFAULT 'Pending'");
    }
};