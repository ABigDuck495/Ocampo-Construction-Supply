<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // no-op: PaymentMethod enum handled in create-transactions migration
    }

    public function down(): void
    {
        // no-op
    }
};