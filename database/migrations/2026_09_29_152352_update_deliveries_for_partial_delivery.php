<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    { 
        DB::statement("
            ALTER TABLE deliveries
            MODIFY Status
            ENUM('Failed', 'Partial', 'Delivered')
            NOT NULL DEFAULT 'Delivered'
        ");

        DB::statement("
            ALTER TABLE deliveries
            MODIFY QuantityDelivered DECIMAL(10,2)
            NOT NULL DEFAULT 0.00
        ");
    }

    public function down(): void
    {
        /*
        | Only run this rollback if there are no Partial deliveries.
        */
        DB::statement("
            UPDATE deliveries
            SET Status = 'Delivered'
            WHERE Status = 'Partial'
        ");

        DB::statement("
            ALTER TABLE deliveries
            MODIFY Status
            ENUM('Failed', 'Delivered')
            NOT NULL DEFAULT 'Delivered'
        ");

        DB::statement("
            ALTER TABLE deliveries
            MODIFY QuantityDelivered INT
            NOT NULL DEFAULT 1
        ");
    }
};