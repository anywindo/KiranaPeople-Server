<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Location extends Model
{
    use HasAuditLog, HasFactory;

    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'radius',
    ];

    /**
     * Cache TTL for location master data (in seconds).
     */
    public const CACHE_TTL = 3600; // 1 hour

    public const CACHE_KEY_ALL = 'master:locations:all';

    protected static function booted(): void
    {
        static::saved(function (Location $location) {
            static::clearMasterCache($location->id);
        });

        static::deleted(function (Location $location) {
            static::clearMasterCache($location->id);
        });
    }

    /**
     * Invalidate all location cache keys.
     */
    public static function clearMasterCache(?int $id = null): void
    {
        Cache::forget(self::CACHE_KEY_ALL);
        if ($id) {
            Cache::forget("master:location:{$id}");
        }
    }

    /**
     * Get all locations with caching.
     */
    public static function getAllCached(): Collection
    {
        return Cache::remember(self::CACHE_KEY_ALL, self::CACHE_TTL, function () {
            return static::orderBy('name')->get();
        });
    }

    /**
     * Find a location by ID with caching.
     */
    public static function findCached(int|string|null $id): ?self
    {
        if (empty($id)) {
            return null;
        }

        return Cache::remember("master:location:{$id}", self::CACHE_TTL, function () use ($id) {
            return static::find($id);
        });
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }
}
