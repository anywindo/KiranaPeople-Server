<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasAuditLog;

    protected $fillable = ['key', 'value', 'type'];

    public const CACHE_KEY = 'kirana_app_settings';

    protected static array $runtimeCache = [];

    protected static function booted(): void
    {
        static::saved(function () {
            static::clearCache();
        });

        static::deleted(function () {
            static::clearCache();
        });
    }

    public static function get($key, $default = null)
    {
        if (array_key_exists($key, self::$runtimeCache)) {
            return self::$runtimeCache[$key];
        }

        // Preload all settings from persistent cache or DB into runtime cache
        if (empty(self::$runtimeCache)) {
            try {
                $cached = \Illuminate\Support\Facades\Cache::rememberForever(self::CACHE_KEY, function () {
                    $all = self::all();
                    $settings = [];
                    foreach ($all as $item) {
                        $settings[$item->key] = self::castValue($item->value, $item->type);
                    }
                    return $settings;
                });

                if (is_array($cached)) {
                    self::$runtimeCache = $cached;
                }
            } catch (\Throwable $e) {
                // Table or cache might not exist during early migrations
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
        $result = self::updateOrCreate(
            ['key' => $key],
            ['value' => is_array($value) ? json_encode($value) : $value, 'type' => $type]
        );

        self::clearCache();

        return $result;
    }

    public static function clearCache(): void
    {
        self::$runtimeCache = [];
        try {
            \Illuminate\Support\Facades\Cache::forget(self::CACHE_KEY);
        } catch (\Throwable $e) {
        }
    }
}
