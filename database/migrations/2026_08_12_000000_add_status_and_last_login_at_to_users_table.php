<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('Status', ['Active', 'Inactive'])->default('Active')->after('Role');
            $table->timestamp('LastLoginAt')->nullable()->after('Status');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['Status', 'LastLoginAt']);
        });
    }
};