<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Shift extends Model
{
    use HasAuditLog, HasFactory;

    protected $guarded = [];

    /**
     * Cache TTL for shift master data (in seconds).
     */
    public const CACHE_TTL = 3600; // 1 hour

    public const CACHE_KEY_ALL = 'master:shifts:all';

    public const CACHE_KEY_FIRST = 'master:shift:first';

    protected static function booted(): void
    {
        static::saved(function (Shift $shift) {
            static::clearMasterCache($shift->id);
        });

        static::deleted(function (Shift $shift) {
            static::clearMasterCache($shift->id);
        });
    }

    /**
     * Invalidate all shift cache keys.
     */
    public static function clearMasterCache(?int $id = null): void
    {
        Cache::forget(self::CACHE_KEY_ALL);
        Cache::forget(self::CACHE_KEY_FIRST);
        if ($id) {
            Cache::forget("master:shift:{$id}");
        }
    }

    /**
     * Get all shifts with caching.
     */
    public static function getAllCached(): Collection
    {
        return Cache::remember(self::CACHE_KEY_ALL, self::CACHE_TTL, function () {
            return static::all();
        });
    }

    /**
     * Get default first shift with caching.
     */
    public static function getFirstCached(): ?self
    {
        return Cache::remember(self::CACHE_KEY_FIRST, self::CACHE_TTL, function () {
            return static::first();
        });
    }

    /**
     * Find a shift by ID with caching.
     */
    public static function findCached(int|string|null $id): ?self
    {
        if (empty($id)) {
            return null;
        }

        return Cache::remember("master:shift:{$id}", self::CACHE_TTL, function () use ($id) {
            return static::find($id);
        });
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }
}
