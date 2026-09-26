<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: widen enum to include old + new values so no data is truncated
        DB::statement("ALTER TABLE trucks MODIFY COLUMN Status ENUM('Available', 'Unavailable', 'Idle', 'Loading', 'On Route', 'Delivered', 'Maintenance') NOT NULL DEFAULT 'Idle'");

        // Step 2: migrate old values to new ones
        DB::table('trucks')->where('Status', 'Available')->update(['Status' => 'Idle']);
        DB::table('trucks')->where('Status', 'Unavailable')->update(['Status' => 'On Route']);

        // Step 3: narrow enum down to only the final list
        DB::statement("ALTER TABLE trucks MODIFY COLUMN Status ENUM('Idle', 'Loading', 'On Route', 'Delivered', 'Maintenance') NOT NULL DEFAULT 'Idle'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE trucks MODIFY COLUMN Status ENUM('Available', 'Unavailable', 'Idle', 'Loading', 'On Route', 'Delivered', 'Maintenance') NOT NULL DEFAULT 'Available'");

        DB::table('trucks')->where('Status', 'Idle')->orWhere('Status', 'Loading')->update(['Status' => 'Available']);
        DB::table('trucks')->whereIn('Status', ['On Route', 'Delivered', 'Maintenance'])->update(['Status' => 'Unavailable']);

        DB::statement("ALTER TABLE trucks MODIFY COLUMN Status ENUM('Available', 'Unavailable') NOT NULL DEFAULT 'Available'");
    }
};