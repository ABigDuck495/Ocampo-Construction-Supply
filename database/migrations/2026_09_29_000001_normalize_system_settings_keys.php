<?php

use App\Models\SystemSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $rows = DB::table('System_Settings')->get();

        foreach ($rows as $row) {
            $rawKey = $row->Setting_Key ?? null;
            $normalizedKey = SystemSetting::normalizeKey($rawKey);

            if ($normalizedKey === null || $normalizedKey === $rawKey) {
                continue;
            }

            $duplicateExists = DB::table('System_Settings')
                ->where('Setting_Key', $normalizedKey)
                ->where('Setting_ID', '!=', $row->Setting_ID)
                ->exists();

            if ($duplicateExists) {
                DB::table('System_Settings')->where('Setting_ID', $row->Setting_ID)->delete();
                continue;
            }

            DB::table('System_Settings')
                ->where('Setting_ID', $row->Setting_ID)
                ->update(['Setting_Key' => $normalizedKey]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No safe reverse mapping for legacy mixed-case keys; this is a normalization fix.
    }
};
