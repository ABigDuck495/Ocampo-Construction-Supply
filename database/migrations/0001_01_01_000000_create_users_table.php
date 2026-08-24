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
        Schema::create('users', function (Blueprint $table) {
            $table->id('UserID');
            $table->string('Name');
            $table->string('Password');
            $table->enum('Role', ['Admin', 'Staff'])->default('Staff');
            $table->enum('Status', ['Active', 'Inactive'])->default('Active');
            $table->timestamp('LastLoginAt')->nullable();
            $table->string('Email')->unique();
            $table->string('PhoneNumber')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
