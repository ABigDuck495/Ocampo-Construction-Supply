<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SystemSetting extends Model
{
    use HasFactory;

    protected $table = 'System_Settings';
    protected $primaryKey = 'Setting_ID';

    protected $fillable = [
        'Setting_Key',
        'Setting_Value',
        'Setting_Group',
        'Setting_Description',
    ];

    protected const CACHE_TTL = 3600;
    protected const CACHE_KEY = 'system_settings.all';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function normalizeKey(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $normalized = strtolower(trim((string) $key));
        $normalized = preg_replace('/[^a-z0-9]+/', '_', $normalized) ?? $normalized;

        return trim($normalized, '_');
    }

    public static function castValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return str_contains((string) $value, '.') ? (float) $value : (int) $value;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === 'true') {
            return true;
        }
        if ($normalized === 'false') {
            return false;
        }

        return $value;
    }

    public static function stringifyValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        return (string) $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $normalizedKey = static::normalizeKey($key);
        $setting = static::query()->get()->first(function ($row) use ($normalizedKey) {
            return static::normalizeKey($row->Setting_Key) === $normalizedKey;
        });

        return $setting ? static::castValue($setting->Setting_Value) : $default;
    }

    public static function set(string $key, mixed $value, ?string $group = null, ?string $description = null): self
    {
        $normalizedKey = static::normalizeKey($key);
        $attributes = ['Setting_Key' => $normalizedKey, 'Setting_Value' => static::stringifyValue($value)];

        if ($group !== null) {
            $attributes['Setting_Group'] = $group;
        }
        if ($description !== null) {
            $attributes['Setting_Description'] = $description;
        }

        return tap(
            static::query()->firstOrNew(['Setting_Key' => $normalizedKey]),
            function (self $setting) use ($attributes) {
                $setting->fill($attributes);
                $setting->save();
            }
        );
    }

    public static function allCached(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return static::query()
                ->get()
                ->mapWithKeys(fn (self $setting) => [
                    static::normalizeKey($setting->Setting_Key) => static::castValue($setting->Setting_Value),
                ])
                ->all();
        });
    }

    public static function grouped(): \Illuminate\Support\Collection
    {
        $groupOrder = ['General', 'Inventory', 'Logistics', 'POS', 'Printer'];

        return static::query()
            ->orderBy('Setting_Group')
            ->orderBy('Setting_Key')
            ->get()
            ->groupBy(function (self $setting) {
                return $setting->attributes['Setting_Group'] ?? 'General';
            })
            ->map(function ($items) {
                return $items->map(function (self $setting) {
                    $setting->attributes['Setting_Key'] = static::normalizeKey($setting->Setting_Key);
                    return $setting;
                });
            })
            ->sortBy(function ($settings, $group) use ($groupOrder) {
                $pos = array_search($group, $groupOrder, true);
                return $pos === false ? count($groupOrder) : $pos;
            });
    }

    public function inputType(): string
    {
        $value = strtolower(trim((string) ($this->attributes['Setting_Value'] ?? '')));

        if (in_array($value, ['true', 'false'], true)) {
            return 'boolean';
        }

        if (is_numeric($value)) {
            return 'number';
        }

        return 'text';
    }

    public function getIsBooleanAttribute(): bool
    {
        return $this->inputType() === 'boolean';
    }

    public function getIsNumericAttribute(): bool
    {
        return $this->inputType() === 'number';
    }

    public function getBoolValueAttribute(): bool
    {
        return strtolower(trim((string) ($this->attributes['Setting_Value'] ?? ''))) === 'true';
    }

    public function getSettingKeyAttribute(): ?string
    {
        return static::normalizeKey($this->attributes['Setting_Key'] ?? null);
    }

    public function setSettingKeyAttribute($value): void
    {
        $this->attributes['Setting_Key'] = static::normalizeKey($value);
    }

    public function getSettingValueAttribute(): ?string
    {
        return $this->attributes['Setting_Value'] ?? null;
    }

    public function setSettingValueAttribute($value): void
    {
        $this->attributes['Setting_Value'] = $value;
    }

    public function getSettingGroupAttribute(): ?string
    {
        return $this->attributes['Setting_Group'] ?? null;
    }

    public function setSettingGroupAttribute($value): void
    {
        $this->attributes['Setting_Group'] = $value;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->attributes['Setting_Description'] ?? null;
    }

    public function setDescriptionAttribute($value): void
    {
        $this->attributes['Setting_Description'] = $value;
    }
}