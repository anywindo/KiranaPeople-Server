<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLogArchive extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'filename',
        'file_path',
        'date_from',
        'date_to',
        'total_records',
        'downloaded_at',
        'purged_at',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'downloaded_at' => 'datetime',
        'purged_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isDownloaded(): bool
    {
        return ! is_null($this->downloaded_at);
    }

    public function isPurged(): bool
    {
        return ! is_null($this->purged_at);
    }

    public function canPurge(): bool
    {
        return $this->isDownloaded() && ! $this->isPurged();
    }
}
