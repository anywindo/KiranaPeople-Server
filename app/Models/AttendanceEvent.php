<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceEvent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'event_type' => \App\Enums\EventType::class,
        'timestamp' => 'datetime',
        'device_timestamp' => 'datetime',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'accuracy' => 'decimal:2',
        'altitude' => 'decimal:2',
        'is_mocked' => 'boolean',
        'device_telemetry' => 'array',
    ];

    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * Get the accessible selfie photo URL (from Cloudflare R2, base64 fallback, or local storage).
     */
    public function getSelfieUrlAttribute(): ?string
    {
        if (empty($this->selfie_path)) {
            if (! empty($this->selfie_base64)) {
                return 'data:image/webp;base64,'.$this->selfie_base64;
            }

            return null;
        }

        if (str_starts_with($this->selfie_path, 'http://') || str_starts_with($this->selfie_path, 'https://')) {
            return $this->selfie_path;
        }

        $r2Url = config('filesystems.disks.r2.url');
        if (! empty($r2Url)) {
            return rtrim($r2Url, '/').'/'.ltrim($this->selfie_path, '/');
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->selfie_path);
    }
}
