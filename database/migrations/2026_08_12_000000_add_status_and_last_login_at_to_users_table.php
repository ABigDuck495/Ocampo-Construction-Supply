<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // no-op: Status and LastLoginAt moved into create-users migration
    }

    public function down(): void
    {
        // no-op
    }
};