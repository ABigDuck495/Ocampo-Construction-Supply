<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // dispatches.Status was missing 'Failed' even though the app has
        // always set it (DispatchController::cancel, and the Failed branch
        // of DeliveryController::store) — add it here, along with the new
        // 'Partial' status for a truckload that was only partly delivered.
        DB::statement("
            ALTER TABLE dispatches
            MODIFY COLUMN Status ENUM('Pending', 'On Route', 'Delivered', 'Partial', 'Failed')
            NOT NULL DEFAULT 'Pending'
        ");

        // deliveries.Status gets the matching 'Partial' value.
        DB::statement("
            ALTER TABLE deliveries
            MODIFY COLUMN Status ENUM('Failed', 'Delivered', 'Partial')
            NOT NULL DEFAULT 'Delivered'
        ");
    }

    public function down(): void
    {
        // Remap any rows using the new values before shrinking the enum,
        // or the ALTER will fail (or MySQL will silently blank them out).
        DB::statement("UPDATE dispatches SET Status = 'Delivered' WHERE Status = 'Partial'");
        DB::statement("
            ALTER TABLE dispatches
            MODIFY COLUMN Status ENUM('Pending', 'On Route', 'Delivered')
            NOT NULL DEFAULT 'Pending'
        ");

        DB::statement("UPDATE deliveries SET Status = 'Delivered' WHERE Status = 'Partial'");
        DB::statement("
            ALTER TABLE deliveries
            MODIFY COLUMN Status ENUM('Failed', 'Delivered')
            NOT NULL DEFAULT 'Delivered'
        ");
    }
};
