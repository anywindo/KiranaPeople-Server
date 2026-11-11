<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasAuditLog;

    protected $fillable = ['key', 'value', 'type'];

    protected static array $runtimeCache = [];

    public static function get($key, $default = null)
    {
        if (array_key_exists($key, self::$runtimeCache)) {
            return self::$runtimeCache[$key];
        }

        // Preload all settings into runtime cache on first call to prevent N+1 queries
        if (empty(self::$runtimeCache)) {
            try {
                $all = self::all();
                foreach ($all as $item) {
                    self::$runtimeCache[$item->key] = self::castValue($item->value, $item->type);
                }
            } catch (\Throwable $e) {
                // Table might not exist during early migrations
            }
        }

        if (array_key_exists($key, self::$runtimeCache)) {
            return self::$runtimeCache[$key];
        }

        $setting = self::where('key', $key)->first();
        if (! $setting) {
            return self::$runtimeCache[$key] = $default;
        }

        return self::$runtimeCache[$key] = self::castValue($setting->value, $setting->type);
    }

    protected static function castValue($value, $type)
    {
        return match ($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'json' => is_array($value) ? $value : json_decode($value, true),
            default => $value,
        };
    }

    public static function set($key, $value, $type = 'string')
    {
        $castValue = self::castValue(is_array($value) ? json_encode($value) : $value, $type);
        self::$runtimeCache[$key] = $castValue;

        return self::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : $value, 'type' => $type]
        );
    }

    public static function clearCache(): void
    {
        self::$runtimeCache = [];
    }
}
