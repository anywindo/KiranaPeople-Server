<?php

namespace App\Models;

use App\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory, HasAuditLog;

    protected $guarded = [];

    protected $casts = [
        'status' => \App\Enums\AttendanceStatus::class,
        'date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function events()
    {
        return $this->hasMany(AttendanceEvent::class)->orderBy('timestamp', 'asc')->orderBy('id', 'asc');
    }

    public function getShiftStatusAttribute()
    {
        $startEvent = $this->events->where('event_type', \App\Enums\EventType::START_SHIFT)->first();
        if (!$startEvent || !$this->shift || !$this->shift->default_start_time) {
            return ['label' => $this->status->label(), 'color' => $this->status->color()];
        }

        $actual = \Carbon\Carbon::parse($startEvent->timestamp)->format('H:i:s');
        $expected = \Carbon\Carbon::parse($this->shift->default_start_time);

        $endEvent = $this->events->where('event_type', \App\Enums\EventType::END_SHIFT)->first();
        $isEarlyOut = false;
        
        if ($endEvent && $this->shift->default_end_time) {
            $actualEnd = \Carbon\Carbon::parse($endEvent->timestamp)->format('H:i:s');
            $expectedEnd = \Carbon\Carbon::parse($this->shift->default_end_time)->format('H:i:s');
            if ($actualEnd < $expectedEnd) {
                $isEarlyOut = true;
            }
        }
        
        $earlyOutSuffix = $isEarlyOut ? ', Pulang awal' : '';

        $ruleEnabled = \App\Models\Setting::get('attendance_rule_enabled', false);
        if ($ruleEnabled) {
            $ruleMinutes = \App\Models\Setting::get('attendance_rule_minutes', 15);
            $cutoffTimeStr = $expected->copy()->subMinutes($ruleMinutes)->format('H:i:s');
            
            if ($actual >= $cutoffTimeStr) {
                return ['label' => 'Terlambat' . $earlyOutSuffix, 'color' => 'danger'];
            }
            return ['label' => 'Hadir' . $earlyOutSuffix, 'color' => $isEarlyOut ? 'warning' : 'success'];
        } else {
            $expectedStr = $expected->format('H:i:s');
            if ($actual <= $expectedStr) {
                return ['label' => 'Hadir' . $earlyOutSuffix, 'color' => $isEarlyOut ? 'warning' : 'success'];
            } else {
                return ['label' => 'Terlambat' . $earlyOutSuffix, 'color' => 'danger'];
            }
        }
    }

    public function getBreakStatusAttribute()
    {
        $startBreakEvent = $this->events->first(fn($e) => ($e->event_type?->value ?? $e->event_type) === 'START_BREAK');
        $endBreakEvent = $this->events->first(fn($e) => ($e->event_type?->value ?? $e->event_type) === 'END_BREAK');
        
        if (!$startBreakEvent || !$this->shift || !$this->shift->break_start || !$this->shift->break_end) {
            return ['label' => '-', 'color' => 'secondary'];
        }
        
        if (!$endBreakEvent) {
            return ['label' => 'Sedang Istirahat', 'color' => 'warning'];
        }

        $actualDuration = \Carbon\Carbon::parse($endBreakEvent->timestamp)->diffInMinutes(\Carbon\Carbon::parse($startBreakEvent->timestamp), true);

        $breakStart = $this->shift->break_start;
        $breakEnd = $this->shift->break_end;

        $isFriday = $this->date ? \Carbon\Carbon::parse($this->date)->isFriday() : \Carbon\Carbon::parse($startBreakEvent->timestamp)->isFriday();
        if ($isFriday && \App\Models\Setting::get('friday_break_enabled', true)) {
            $breakStart = \App\Models\Setting::get('friday_break_start', '11:30');
            $breakEnd = \App\Models\Setting::get('friday_break_end', '13:00');
        }

        $expectedStartTime = \Carbon\Carbon::parse($breakStart)->format('H:i:s');
        $expectedEndTime = \Carbon\Carbon::parse($breakEnd)->format('H:i:s');
        $actualStartTime = \Carbon\Carbon::parse($startBreakEvent->timestamp)->format('H:i:s');
        $actualEndTime = \Carbon\Carbon::parse($endBreakEvent->timestamp)->format('H:i:s');

        $expectedDuration = \Carbon\Carbon::parse($breakEnd)->diffInMinutes(\Carbon\Carbon::parse($breakStart), true);

        $isEarlyBreak = ($actualStartTime < $expectedStartTime);
        $isLateBreak = ($actualStartTime > $expectedEndTime)
            || ($actualEndTime > $expectedEndTime)
            || ($actualDuration > $expectedDuration);

        if ($isEarlyBreak) {
            return [
                'label' => $isLateBreak ? 'Terlambat, Istirahat Awal' : 'Istirahat Awal',
                'color' => 'danger',
            ];
        }

        if ($isLateBreak) {
            return ['label' => 'Terlambat', 'color' => 'danger'];
        }

        return ['label' => 'Tepat Waktu', 'color' => 'success'];
    }
}
