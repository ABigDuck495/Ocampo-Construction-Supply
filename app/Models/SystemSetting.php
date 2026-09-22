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
        // map to actual DB column names (existing migration uses PascalCase)
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

  
    public static function get(string $key, mixed $default = null): mixed
    {
        $setting = static::query()->where('Setting_Key', $key)->first();

        return $setting ? static::castValue($setting->getAttribute('Setting_Value')) : $default;
    }

 
    public static function set(string $key, mixed $value, ?string $group = null, ?string $description = null): self
    {
        $attributes = ['Setting_Value' => static::stringifyValue($value)];

        if ($group !== null) {
            $attributes['Setting_Group'] = $group;
        }
        if ($description !== null) {
            $attributes['Setting_Description'] = $description;
        }

        return tap(
            static::query()->firstOrNew(['Setting_Key' => $key]),
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
                    $setting->setting_key => static::castValue($setting->setting_value),
                ])
                ->all();
        });
    }

 
    public static function grouped(): \Illuminate\Support\Collection
    {
        
        $groupOrder = ['General', 'Inventory', 'Logistics', 'POS', 'Printer'];
      
        // Use the actual DB column names for ordering/grouping (migration uses PascalCase)
        return static::query()
            ->orderBy('Setting_Group')
            ->orderBy('Setting_Key')
            
            ->groupBy(function (self $setting) {
                return $setting->getAttribute('Setting_Group') ?? 'General';
            })
            ->sortBy(function ($settings, $group) use ($groupOrder) {
                $pos = array_search($group, $groupOrder, true);
                return $pos === false ? count($groupOrder) : $pos;
            })->get();
    }

 
    public function inputType(): string
    {
        $value = strtolower(trim((string) $this->getAttribute('Setting_Value')));

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
        return strtolower(trim((string) $this->getAttribute('Setting_Value'))) === 'true';
    }

    // Accessors & mutators to map PascalCase DB columns to snake_case properties
    public function getSettingKeyAttribute(): ?string
    {
        return $this->getAttribute('Setting_Key') ?? null;
    }

    public function setSettingKeyAttribute($value): void
    {
        $this->attributes['Setting_Key'] = $value;
    }

    public function getSettingValueAttribute(): ?string
    {
        return $this->getAttribute('Setting_Value') ?? null;
    }

    public function setSettingValueAttribute($value): void
    {
        $this->attributes['Setting_Value'] = $value;
    }

    public function getSettingGroupAttribute(): ?string
    {
        return $this->getAttribute('Setting_Group') ?? null;
    }

    public function setSettingGroupAttribute($value): void
    {
        $this->attributes['Setting_Group'] = $value;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->getAttribute('Setting_Description') ?? null;
    }

    public function setDescriptionAttribute($value): void
    {
        $this->attributes['Setting_Description'] = $value;
    }

 
    protected static function castValue(string $value): mixed
    {
        $trimmed = trim($value);
        $lower = strtolower($trimmed);

        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if (is_numeric($trimmed)) {
            return str_contains($trimmed, '.') ? (float) $trimmed : (int) $trimmed;
        }

        return $value;
    }

    protected static function stringifyValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}