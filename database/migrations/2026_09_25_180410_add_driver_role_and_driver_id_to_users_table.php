<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Add Driver to the users Role enum
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE users
            MODIFY Role ENUM('Admin', 'Staff', 'Driver')
            NOT NULL DEFAULT 'Staff'
        ");

        /*
        |--------------------------------------------------------------------------
        | Add DriverID
        |--------------------------------------------------------------------------
        */

        Schema::table('users', function (Blueprint $table) {

            $table->unsignedBigInteger('DriverID')
                ->nullable()
                ->after('Role');

            $table->foreign('DriverID')
                ->references('DriverID')
                ->on('drivers')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Remove DriverID foreign key and column
        |--------------------------------------------------------------------------
        */

        Schema::table('users', function (Blueprint $table) {

            $table->dropForeign(['DriverID']);

            $table->dropColumn('DriverID');
        });

        /*
        |--------------------------------------------------------------------------
        | Restore original Role enum
        |--------------------------------------------------------------------------
        */

        DB::statement("
            ALTER TABLE users
            MODIFY Role ENUM('Admin', 'Staff')
            NOT NULL DEFAULT 'Staff'
        ");
    }
};