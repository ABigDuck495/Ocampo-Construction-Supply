<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // no-op: bluetooth_com_port moved into create-printers migration
    }

    public function down(): void
    {
        // no-op
    }
};
