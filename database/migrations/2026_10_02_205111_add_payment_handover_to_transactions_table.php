<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('ReceivedBy')->nullable();
            $table->string('HandedOverBy')->nullable();
            $table->timestamp('PaymentUpdatedAt')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['ReceivedBy', 'HandedOverBy', 'PaymentUpdatedAt']);
        });
    }
};
