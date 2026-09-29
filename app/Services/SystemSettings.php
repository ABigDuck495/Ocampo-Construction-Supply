<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

class SystemSettings
{
    protected array $settings = [];

    public function __construct()
    {
        $this->settings = DB::table('System_Settings')
            ->get()
            ->reduce(function (array $carry, $row) {
                $key = SystemSetting::normalizeKey($row->Setting_Key ?? null);
                if ($key === null) {
                    return $carry;
                }

                $carry[$key] = SystemSetting::castValue($row->Setting_Value ?? null);
                return $carry;
            }, []);
    }

    public function isEnabled(string $key): bool
    {
        $normalizedKey = SystemSetting::normalizeKey($key);
        $v = $this->settings[$normalizedKey] ?? null;

        if (is_null($v)) {
            return false;
        }

        if (is_string($v)) {
            $lv = strtolower(trim($v));
            return in_array($lv, ['1', 'true', 'on', 'yes'], true);
        }

        return (bool) $v;
    }

    public function get(string $key, $default = null)
    {
        $normalizedKey = SystemSetting::normalizeKey($key);

        return $this->settings[$normalizedKey] ?? $default;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $val = $this->get($key, $default);
        if (is_null($val)) {
            return $default;
        }

        return (float) str_replace(',', '.', (string) $val);
    }

    public function all(): array
    {
        return $this->settings;
    }
}
