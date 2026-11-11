<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasAuditLog, HasFactory, HasRoles, Notifiable;

    /**
     * The guard name for Spatie permissions.
     */
    protected string $guard_name = 'web';

    /**
     * Mass assignable attributes.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'employee_id',
        'nik',
        'dob',
        'location_id',
        'avatar',
        'is_active',
        'password_changed_at',
    ];

    /**
     * Hidden from serialization (never expose in JSON responses).
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'avatar_url',
    ];

    /**
     * Type casts.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'dob' => 'date',
            'password' => 'hashed',
            'password_changed_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Check if user must change their default password.
     */
    public function mustChangePassword(): bool
    {
        if ($this->isSuperAdmin()) {
            return false;
        }

        return $this->password_changed_at === null;
    }

    /**
     * Check if user has already changed their password.
     */
    public function hasChangedPassword(): bool
    {
        return $this->password_changed_at !== null;
    }

    /**
     * Model booted events.
     */
    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->employee_id)) {
                $user->employee_id = static::generateNextEmployeeId();
            }
        });
    }

    /**
     * Accessor for employee_id with automatic fallback to padded ID if not set.
     */
    public function getEmployeeIdAttribute($value): ?string
    {
        if (! empty($value)) {
            return $value;
        }

        if ($this->exists && $this->id) {
            return 'EMP-'.str_pad($this->id, 5, '0', STR_PAD_LEFT);
        }

        return null;
    }

    // =========================================================================
    // Relationships
    // =========================================================================

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get user's assigned work location using master data cache.
     */
    public function getLocationCached(): ?Location
    {
        return $this->location_id ? Location::findCached($this->location_id) : null;
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function attendanceCorrectionRequests()
    {
        return $this->hasMany(AttendanceCorrectionRequest::class);
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'team_user');
    }

    public function shifts()
    {
        return Shift::query();
    }

    public function getShiftAttribute()
    {
        return $this->attendances()->latest('date')->first()?->shift ?? Shift::first();
    }

    // =========================================================================
    // Scopes
    // =========================================================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    public function isAdmin(): bool
    {
        return $this->hasRole(['super-admin', 'admin', 'HR']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-admin');
    }

    /**
     * Find a user by email, employee ID (with/without prefix), user primary key ID, or NIK.
     */
    public static function findByLogin(?string $login): ?self
    {
        if (blank($login)) {
            return null;
        }

        $login = trim($login);

        return static::where(function ($query) use ($login) {
            $query->where('email', $login)
                ->orWhere('employee_id', $login)
                ->orWhere('nik', $login);

            // Case-insensitive employee_id match
            $query->orWhereRaw('LOWER(employee_id) = ?', [strtolower($login)]);

            // Numeric input (e.g. user id 329, 1, or numeric part of employee_id)
            if (is_numeric($login)) {
                $id = (int) $login;
                $query->orWhere('id', $id)
                    ->orWhere('employee_id', 'EMP-'.$login)
                    ->orWhere('employee_id', 'EMP'.$login)
                    ->orWhere('employee_id', 'EMP'.str_pad($login, 4, '0', STR_PAD_LEFT))
                    ->orWhere('employee_id', 'EMP-'.str_pad($login, 5, '0', STR_PAD_LEFT));
            }

            // Prefixed employee ID input (e.g. EMP0329, EMP-0329, emp49180)
            if (preg_match('/^EMP-?([0-9]+)$/i', $login, $matches)) {
                $num = (int) $matches[1];
                $query->orWhere('id', $num)
                    ->orWhere('employee_id', 'EMP-'.$matches[1])
                    ->orWhere('employee_id', 'EMP'.$matches[1])
                    ->orWhere('employee_id', 'EMP'.str_pad($num, 4, '0', STR_PAD_LEFT))
                    ->orWhere('employee_id', 'EMP-'.str_pad($num, 5, '0', STR_PAD_LEFT));
            }
        })->first();
    }

    /**
     * Parse 16-digit Indonesian NIK to birth date components.
     * Digits 7-8: Day (DD, +40 for female)
     * Digits 9-10: Month (MM)
     * Digits 11-12: Year 2 digits (YY)
     *
     * @return array{day: int, month: int, year: int, dmy: string, ymd: string}|null
     */
    public static function parseNikBirthDetails(?string $nik): ?array
    {
        if (blank($nik)) {
            return null;
        }

        $cleanNik = preg_replace('/[^0-9]/', '', (string) $nik);
        if (strlen($cleanNik) !== 16) {
            return null;
        }

        $rawDay = (int) substr($cleanNik, 6, 2);
        $month = (int) substr($cleanNik, 8, 2);
        $year2 = (int) substr($cleanNik, 10, 2);

        $day = $rawDay > 40 ? ($rawDay - 40) : $rawDay;

        // Current 2-digit year (e.g. 26 for 2026)
        $currentYear2 = (int) date('y');
        $fullYear = ($year2 <= $currentYear2) ? (2000 + $year2) : (1900 + $year2);

        if (! checkdate($month, $day, $fullYear)) {
            return null;
        }

        return [
            'day' => $day,
            'month' => $month,
            'year' => $fullYear,
            'dmy' => sprintf('%02d%02d%04d', $day, $month, $fullYear),
            'ymd' => sprintf('%04d-%02d-%02d', $fullYear, $month, $day),
        ];
    }

    /**
     * Parse birthday from Indonesian NIK and format as DDMMYYYY.
     */
    public static function parseBirthdayFromNik(?string $nik): ?string
    {
        $details = static::parseNikBirthDetails($nik);

        return $details ? $details['dmy'] : null;
    }

    /**
     * Parse date of birth from Indonesian NIK formatted as Y-m-d.
     */
    public static function parseDobDateFromNik(?string $nik): ?string
    {
        $details = static::parseNikBirthDetails($nik);

        return $details ? $details['ymd'] : null;
    }

    /**
     * Get default password for employee (DDMMYYYY from dob or NIK, fallback to 'password').
     */
    public function getDefaultPassword(): string
    {
        if ($this->dob) {
            return $this->dob->format('dmY');
        }

        $fromNik = static::parseBirthdayFromNik($this->nik);
        if ($fromNik) {
            return $fromNik;
        }

        return 'password';
    }

    /**
     * Static helper to compute default password for a given dob or NIK.
     */
    public static function computeDefaultPassword($dob = null, ?string $nik = null): string
    {
        if ($dob) {
            if ($dob instanceof \Carbon\CarbonInterface || $dob instanceof \DateTimeInterface) {
                return $dob->format('dmY');
            }
            try {
                return \Illuminate\Support\Carbon::parse($dob)->format('dmY');
            } catch (\Throwable $e) {
                // fall through
            }
        }

        $fromNik = static::parseBirthdayFromNik($nik);
        if ($fromNik) {
            return $fromNik;
        }

        return 'password';
    }

    /**
     * Generate next sequential employee ID (e.g. EMP-00331).
     */
    public static function generateNextEmployeeId(): string
    {
        $maxId = (int) (static::max('id') ?? 0);
        $nextId = $maxId + 1;
        $candidate = 'EMP-'.str_pad($nextId, 5, '0', STR_PAD_LEFT);
        while (static::where('employee_id', $candidate)->exists()) {
            $nextId++;
            $candidate = 'EMP-'.str_pad($nextId, 5, '0', STR_PAD_LEFT);
        }

        return $candidate;
    }

    /**
     * Get computed avatar URL with UI-Avatars initials fallback, bypassing disk checks.
     */
    public function getAvatarUrlAttribute(): string
    {
        if (! empty($this->avatar) && ! str_contains($this->avatar, 'userdefault')) {
            if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
                return $this->avatar;
            }

            $r2Url = config('filesystems.disks.r2.url');
            if (! empty($r2Url)) {
                $clean = ltrim($this->avatar, '/');
                $subPath = str_starts_with($clean, 'storage/') ? substr($clean, 8) : $clean;

                return rtrim($r2Url, '/').'/'.ltrim($subPath, '/');
            }

            $clean = ltrim($this->avatar, '/');
            $fullPath = str_starts_with($clean, 'storage/') ? $clean : 'storage/'.$clean;

            return asset($fullPath).'?v=3';
        }

        $cleanName = trim($this->name ?: 'User');
        $encoded = urlencode($cleanName);

        return "https://ui-avatars.com/api/?name={$encoded}&background=4F46E5&color=fff&size=256&bold=true";
    }
}
