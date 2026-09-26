<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'Username')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('Username')->nullable()->after('Name');
            });
        }

        DB::table('users')->orderBy('UserID')->each(function ($user) {
            if (empty($user->Username)) {
                DB::table('users')
                    ->where('UserID', $user->UserID)
                    ->update(['Username' => 'user' . $user->UserID]);
            }
        });

        if (!$this->indexExists('users', 'users_username_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('Username', 'users_username_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_username_unique');
            $table->dropColumn('Username');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};