<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Widen first so no existing rows get truncated, then narrow to the final set
        DB::statement("ALTER TABLE dispatches MODIFY COLUMN Status ENUM('Pending', 'On Route', 'Delivered', 'Failed') NOT NULL DEFAULT 'Pending'");

        Schema::table('dispatches', function (Blueprint $table) {
            $table->timestamp('AcceptedAt')->nullable()->after('DispatchDate');
        });

        Schema::create('dispatch_logs', function (Blueprint $table) {
            $table->id('DispatchLogID');
            $table->unsignedBigInteger('DispatchID');
            $table->enum('Action', ['Dispatched', 'Accepted', 'Delivered', 'PartiallyDelivered', 'Failed']);
            $table->text('Notes')->nullable();
            $table->timestamp('LoggedAt')->useCurrent();

            $table->foreign('DispatchID')->references('DispatchID')->on('dispatches')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_logs');

        Schema::table('dispatches', function (Blueprint $table) {
            $table->dropColumn('AcceptedAt');
        });
    }
};