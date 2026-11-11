<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'before',
        'after',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    const UPDATED_AT = null;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    /**
     * Decode JSON attribute safely, unrolling legacy double-encoded JSON and filtering sensitive keys.
     */
    protected function decodeJsonAttribute($value): ?array
    {
        if (is_null($value)) {
            return null;
        }

        if (empty($value)) {
            return [];
        }

        $decoded = is_string($value) ? json_decode($value, true) : $value;

        // If it was double-encoded in older records, decode again
        while (is_string($decoded)) {
            $further = json_decode($decoded, true);
            if (! is_array($further)) {
                break;
            }
            $decoded = $further;
        }

        if (! is_array($decoded)) {
            return [];
        }

        // Clean any sensitive fields from old legacy records
        $ignored = ['password', 'remember_token', 'avatar_base64', 'selfie_base64'];

        return array_diff_key($decoded, array_flip($ignored));
    }

    public function getBeforeAttribute($value): ?array
    {
        return $this->decodeJsonAttribute($value);
    }

    public function getAfterAttribute($value): ?array
    {
        return $this->decodeJsonAttribute($value);
    }

    public function setBeforeAttribute($value): void
    {
        $this->attributes['before'] = is_array($value) ? json_encode($value) : $value;
    }

    public function setAfterAttribute($value): void
    {
        $this->attributes['after'] = is_array($value) ? json_encode($value) : $value;
    }

    /**
     * Human-friendly label for auditable model type.
     */
    public function getModelLabelAttribute(): string
    {
        return match ($this->auditable_type) {
            'App\Models\User' => 'Karyawan / User',
            'App\Models\Shift' => 'Pengaturan Shift',
            'App\Models\Location' => 'Lokasi Kerja (GPS)',
            'App\Models\Attendance' => 'Presensi',
            'App\Models\Setting' => 'Pengaturan Sistem',
            'App\Models\Role', 'Spatie\Permission\Models\Role' => 'Hak Akses (Role)',
            'App\Models\Team' => 'Departemen / Tim',
            default => class_basename($this->auditable_type ?? 'Data'),
        };
    }

    /**
     * Bootstrap badge HTML for the CRUD event.
     */
    public function getEventBadgeAttribute(): string
    {
        return match ($this->event) {
            'created' => '<span class="badge bg-success"><i class="bi bi-plus-circle me-1"></i> Created</span>',
            'updated' => '<span class="badge bg-warning text-dark"><i class="bi bi-pencil-square me-1"></i> Updated</span>',
            'deleted' => '<span class="badge bg-danger"><i class="bi bi-trash me-1"></i> Deleted</span>',
            default => '<span class="badge bg-secondary">'.e(ucfirst($this->event)).'</span>',
        };
    }
}
