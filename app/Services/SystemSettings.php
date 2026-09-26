<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SystemSettings
{
    protected array $settings = [];

    public function __construct()
    {
        // load and cache settings as key => value
        $this->settings = DB::table('System_Settings')->pluck('Setting_Value', 'Setting_Key')->toArray();
    }

    public function isEnabled(string $key): bool
    {
        $v = $this->settings[$key] ?? null;
        if (is_null($v)) return false;
        if (is_string($v)) {
            $lv = strtolower(trim($v));
            return in_array($lv, ['1', 'true', 'on', 'yes'], true);
        }
        return (bool) $v;
    }

    public function get(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        $val = $this->get($key, $default);
        if (is_null($val)) return $default;
        return (float) str_replace(',', '.', (string) $val);
    }

    public function all(): array
    {
        return $this->settings;
    }
}
