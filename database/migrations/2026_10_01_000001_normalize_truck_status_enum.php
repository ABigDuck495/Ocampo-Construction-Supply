<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE trucks MODIFY COLUMN Status ENUM('Idle', 'Loading', 'On Route', 'Delivered', 'Maintenance') NOT NULL DEFAULT 'Idle'");

        DB::table('trucks')
            ->whereNotIn('Status', ['Idle', 'Loading', 'On Route', 'Delivered', 'Maintenance'])
            ->update(['Status' => 'Idle']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE trucks MODIFY COLUMN Status ENUM('Available', 'Unavailable', 'Idle', 'Loading', 'On Route', 'Delivered', 'Maintenance') NOT NULL DEFAULT 'Available'");

        DB::table('trucks')
            ->whereIn('Status', ['Idle', 'Loading', 'On Route', 'Delivered', 'Maintenance'])
            ->update(['Status' => 'Available']);

        DB::statement("ALTER TABLE trucks MODIFY COLUMN Status ENUM('Available', 'Unavailable') NOT NULL DEFAULT 'Available'");
    }
};
