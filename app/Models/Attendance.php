<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Enums\EventType;
use App\Traits\HasAuditLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasAuditLog, HasFactory;

    protected $guarded = [];

    protected $casts = [
        'status' => AttendanceStatus::class,
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
        if (isset($this->is_absent) && $this->is_absent) {
            return ['label' => 'Mangkir / Belum Presensi', 'color' => 'danger'];
        }

        $startEvent = $this->events->where('event_type', EventType::START_SHIFT)->first();
        if (! $startEvent || ! $this->shift || ! $this->shift->default_start_time) {
            return ['label' => $this->status ? $this->status->label() : 'Mangkir / Belum Presensi', 'color' => $this->status ? $this->status->color() : 'danger'];
        }

        $actual = Carbon::parse($startEvent->timestamp)->format('H:i:s');
        $expected = Carbon::parse($this->shift->default_start_time);

        $endEvent = $this->events->where('event_type', EventType::END_SHIFT)->first();
        $isEarlyOut = false;

        if ($endEvent && $this->shift->default_end_time) {
            $actualEnd = Carbon::parse($endEvent->timestamp)->format('H:i:s');
            $expectedEnd = Carbon::parse($this->shift->default_end_time)->format('H:i:s');
            if ($actualEnd < $expectedEnd) {
                $isEarlyOut = true;
            }
        }

        $earlyOutSuffix = $isEarlyOut ? ', Pulang awal' : '';

        $ruleEnabled = Setting::get('attendance_rule_enabled', false);
        if ($ruleEnabled) {
            $ruleMinutes = Setting::get('attendance_rule_minutes', 15);
            $cutoffTimeStr = $expected->copy()->subMinutes($ruleMinutes)->format('H:i:s');

            if ($actual >= $cutoffTimeStr) {
                return ['label' => 'Terlambat'.$earlyOutSuffix, 'color' => 'danger'];
            }

            return ['label' => 'Hadir'.$earlyOutSuffix, 'color' => $isEarlyOut ? 'warning' : 'success'];
        } else {
            $expectedStr = $expected->format('H:i:s');
            if ($actual <= $expectedStr) {
                return ['label' => 'Hadir'.$earlyOutSuffix, 'color' => $isEarlyOut ? 'warning' : 'success'];
            } else {
                return ['label' => 'Terlambat'.$earlyOutSuffix, 'color' => 'danger'];
            }
        }
    }

    public function getBreakStatusAttribute()
    {
        $startBreakEvent = $this->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === 'START_BREAK');
        $endBreakEvent = $this->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === 'END_BREAK');

        if (! $startBreakEvent || ! $this->shift || ! $this->shift->break_start || ! $this->shift->break_end) {
            return ['label' => '-', 'color' => 'secondary'];
        }

        if (! $endBreakEvent) {
            return ['label' => 'Sedang Istirahat', 'color' => 'warning'];
        }

        $actualDuration = Carbon::parse($endBreakEvent->timestamp)->diffInMinutes(Carbon::parse($startBreakEvent->timestamp), true);

        $breakStart = $this->shift->break_start;
        $breakEnd = $this->shift->break_end;

        $isFriday = $this->date ? Carbon::parse($this->date)->isFriday() : Carbon::parse($startBreakEvent->timestamp)->isFriday();
        if ($isFriday && Setting::get('friday_break_enabled', true)) {
            $breakStart = Setting::get('friday_break_start', '11:30');
            $breakEnd = Setting::get('friday_break_end', '13:00');
        }

        $expectedStartTime = Carbon::parse($breakStart)->format('H:i:s');
        $expectedEndTime = Carbon::parse($breakEnd)->format('H:i:s');
        $actualStartTime = Carbon::parse($startBreakEvent->timestamp)->format('H:i:s');
        $actualEndTime = Carbon::parse($endBreakEvent->timestamp)->format('H:i:s');

        $expectedDuration = Carbon::parse($breakEnd)->diffInMinutes(Carbon::parse($breakStart), true);

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

    public function getCheckInEventAttribute()
    {
        return $this->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === EventType::START_SHIFT->value || $e->event_type === EventType::START_SHIFT);
    }

    public function getCheckOutEventAttribute()
    {
        return $this->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === EventType::END_SHIFT->value || $e->event_type === EventType::END_SHIFT);
    }

    public function getCheckInEvaluationAttribute(): array
    {
        $checkIn = $this->check_in_event;
        $scheduledStart = $this->shift?->default_start_time ? Carbon::parse($this->shift->default_start_time) : null;
        $actualStart = $checkIn ? $checkIn->timestamp : null;

        $isLate = false;
        $isWarning = false;

        if ($checkIn && $scheduledStart) {
            $ruleEnabled = (bool) Setting::get('attendance_rule_enabled', false);
            $ruleMinutes = $ruleEnabled ? (int) Setting::get('attendance_rule_minutes', 15) : 0;
            if ($ruleEnabled && $ruleMinutes > 0) {
                $cutoff = $scheduledStart->copy()->subMinutes($ruleMinutes);
                if ($actualStart->format('H:i:s') >= $cutoff->format('H:i:s')) {
                    $isLate = true;
                }
            } else {
                $isLate = $actualStart->format('H:i:s') > $scheduledStart->format('H:i:s');
            }
        }

        $label = $isWarning ? 'Hampir Terlambat' : ($isLate ? 'Terlambat' : 'Tepat Waktu');
        $color = $isWarning ? 'warning' : ($isLate ? 'danger' : 'success');

        return [
            'event' => $checkIn,
            'time' => $checkIn ? $actualStart->format('H:i') : '-',
            'is_warning' => $isWarning,
            'is_late' => $isLate,
            'label' => $label,
            'color' => $color,
            'photo' => $checkIn && $checkIn->selfie_url ? $checkIn->selfie_url : asset('placeholder.svg'),
            'lat' => $checkIn ? $checkIn->latitude : '',
            'lng' => $checkIn ? $checkIn->longitude : '',
        ];
    }

    public function getCheckOutEvaluationAttribute(): array
    {
        $checkOut = $this->check_out_event;
        $scheduledEnd = $this->shift?->default_end_time ? Carbon::parse($this->shift->default_end_time) : null;
        $actualEnd = $checkOut ? $checkOut->timestamp : null;

        $isEarly = $checkOut && $scheduledEnd && $actualEnd->format('H:i') < $scheduledEnd->format('H:i');
        $label = $isEarly ? 'Pulang Cepat' : 'Tepat Waktu';
        $color = $isEarly ? 'warning' : 'success';

        return [
            'event' => $checkOut,
            'time' => $checkOut ? $actualEnd->format('H:i') : '-',
            'is_early' => $isEarly,
            'label' => $label,
            'color' => $color,
            'photo' => $checkOut && $checkOut->selfie_url ? $checkOut->selfie_url : asset('placeholder.svg'),
            'lat' => $checkOut ? $checkOut->latitude : '',
            'lng' => $checkOut ? $checkOut->longitude : '',
        ];
    }

    public function getDetailModalDataAttribute(): array
    {
        if (isset($this->is_absent) && $this->is_absent) {
            return [
                'employee' => $this->user->name ?? '-',
                'date' => $this->date ? $this->date->format('d M Y') : '-',
                'shiftStatusLabel' => 'Mangkir / Belum Presensi',
                'shiftStatusColor' => 'danger',
                'in' => [
                    'time' => '-',
                    'statusLabel' => 'Belum Presensi',
                    'statusColor' => 'danger',
                    'photo' => asset('placeholder.svg'),
                    'lat' => '',
                    'lng' => '',
                ],
                'out' => [
                    'time' => '-',
                    'statusLabel' => 'Belum Presensi',
                    'statusColor' => 'danger',
                    'photo' => asset('placeholder.svg'),
                    'lat' => '',
                    'lng' => '',
                ],
            ];
        }

        $inEval = $this->check_in_evaluation;
        $outEval = $this->check_out_evaluation;
        $shiftStatus = $this->shift_status;

        return [
            'employee' => $this->user->name ?? '-',
            'date' => $this->date ? $this->date->format('d M Y') : '-',
            'shiftStatusLabel' => $shiftStatus['label'],
            'shiftStatusColor' => $shiftStatus['color'],
            'in' => [
                'time' => $inEval['time'],
                'statusLabel' => $inEval['label'],
                'statusColor' => $inEval['color'],
                'photo' => $inEval['photo'],
                'lat' => $inEval['lat'],
                'lng' => $inEval['lng'],
            ],
            'out' => [
                'time' => $outEval['time'],
                'statusLabel' => $outEval['label'],
                'statusColor' => $outEval['color'],
                'photo' => $outEval['photo'],
                'lat' => $outEval['lat'],
                'lng' => $outEval['lng'],
            ],
        ];
    }

    public function getBreakEvaluationAttribute(): array
    {
        if (isset($this->is_absent) && $this->is_absent) {
            return [
                'start_break' => null,
                'end_break' => null,
                'duration' => '-',
                'start_color_class' => 'text-muted',
                'end_color_class' => 'text-muted',
                'in_photo' => asset('placeholder.svg'),
                'out_photo' => asset('placeholder.svg'),
                'break_status_label' => 'Mangkir / Belum Presensi',
                'break_status_color' => 'danger',
                'detail_data' => [
                    'employee' => $this->user->name ?? '-',
                    'date' => $this->date ? Carbon::parse($this->date)->format('d M Y') : '-',
                    'shiftStatusLabel' => 'Mangkir / Belum Presensi',
                    'shiftStatusColor' => 'danger',
                    'in' => [
                        'time' => '-',
                        'statusLabel' => '-',
                        'statusColor' => 'secondary',
                        'photo' => asset('placeholder.svg'),
                        'lat' => null,
                        'lng' => null,
                    ],
                    'out' => [
                        'time' => '-',
                        'statusLabel' => '-',
                        'statusColor' => 'secondary',
                        'photo' => asset('placeholder.svg'),
                        'lat' => null,
                        'lng' => null,
                    ],
                ],
            ];
        }

        $startBreak = $this->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === EventType::START_BREAK->value || $e->event_type === EventType::START_BREAK);
        $endBreak = $this->events->first(fn ($e) => ($e->event_type?->value ?? $e->event_type) === EventType::END_BREAK->value || $e->event_type === EventType::END_BREAK);

        $duration = '-';
        if ($startBreak && $endBreak) {
            $diff = Carbon::parse($endBreak->timestamp)->diff(Carbon::parse($startBreak->timestamp));
            $duration = sprintf('%02d:%02d:%02d', $diff->h, $diff->i, $diff->s);
        }

        if (! $startBreak && ! $endBreak) {
            $breakStatusLabel = 'Tidak Istirahat';
            $breakStatusColor = 'secondary';
        } else {
            $breakStatus = $this->break_status;
            $breakStatusLabel = is_array($breakStatus) ? ($breakStatus['label'] ?? '-') : ($breakStatus ?? '-');
            $breakStatusColor = is_array($breakStatus) ? ($breakStatus['color'] ?? 'secondary') : 'secondary';
        }
        $startColorClass = 'text-muted';
        $endColorClass = 'text-muted';
        $isEarlyBreak = false;
        $isLateStartBreak = false;
        $isLateBreak = false;

        if ($startBreak && $this->shift && $this->shift->break_start && $this->shift->break_end) {
            $startColorClass = 'text-warning';
            $endColorClass = 'text-info';

            $breakStartCfg = $this->shift->break_start;
            $breakEndCfg = $this->shift->break_end;
            $isFriday = $this->date ? Carbon::parse($this->date)->isFriday() : Carbon::parse($startBreak->timestamp)->isFriday();
            if ($isFriday && Setting::get('friday_break_enabled', true)) {
                $breakStartCfg = Setting::get('friday_break_start', '11:30');
                $breakEndCfg = Setting::get('friday_break_end', '13:00');
            }

            $actualStartTime = Carbon::parse($startBreak->timestamp)->format('H:i:s');
            $expectedStartTime = Carbon::parse($breakStartCfg)->format('H:i:s');
            $expectedEndTime = Carbon::parse($breakEndCfg)->format('H:i:s');

            $isEarlyBreak = ($actualStartTime < $expectedStartTime);
            $isLateStartBreak = ($actualStartTime > $expectedEndTime);
            $startColorClass = ($isEarlyBreak || $isLateStartBreak) ? 'text-danger' : 'text-success';

            if ($endBreak) {
                $actualEndTime = Carbon::parse($endBreak->timestamp)->format('H:i:s');
                $actualDuration = Carbon::parse($endBreak->timestamp)->diffInMinutes(Carbon::parse($startBreak->timestamp), true);
                $expectedDuration = Carbon::parse($breakEndCfg)->diffInMinutes(Carbon::parse($breakStartCfg), true);

                $isLateBreak = ($actualEndTime > $expectedEndTime)
                    || ($actualEndTime < $expectedStartTime)
                    || ($actualDuration > $expectedDuration);
                $endColorClass = $isLateBreak ? 'text-danger' : 'text-success';
            }
        }

        $inPhoto = $startBreak && $startBreak->selfie_url ? $startBreak->selfie_url : asset('placeholder.svg');
        $outPhoto = $endBreak && $endBreak->selfie_url ? $endBreak->selfie_url : asset('placeholder.svg');

        $inStatusLabel = $startBreak ? ($isEarlyBreak ? 'Awal' : ($isLateStartBreak ? 'Terlambat' : 'Tepat')) : '-';
        $inStatusColor = $startBreak ? (($isEarlyBreak || $isLateStartBreak) ? 'danger' : 'success') : 'secondary';

        $outStatusLabel = $endBreak ? ($isLateBreak ? 'Terlambat' : 'Tepat') : '-';
        $outStatusColor = $endBreak ? ($isLateBreak ? 'danger' : 'success') : 'secondary';

        $detailData = [
            'employee' => $this->user->name ?? '-',
            'date' => Carbon::parse($this->date)->format('d M Y'),
            'shiftStatusLabel' => $breakStatusLabel,
            'shiftStatusColor' => $breakStatusColor,
            'in' => [
                'time' => $startBreak ? Carbon::parse($startBreak->timestamp)->format('H:i') : '-',
                'statusLabel' => $inStatusLabel,
                'statusColor' => $inStatusColor,
                'photo' => $inPhoto,
                'lat' => $startBreak->latitude ?? null,
                'lng' => $startBreak->longitude ?? null,
            ],
            'out' => [
                'time' => $endBreak ? Carbon::parse($endBreak->timestamp)->format('H:i') : '-',
                'statusLabel' => $outStatusLabel,
                'statusColor' => $outStatusColor,
                'photo' => $outPhoto,
                'lat' => $endBreak->latitude ?? null,
                'lng' => $endBreak->longitude ?? null,
            ],
        ];

        return [
            'start_break' => $startBreak,
            'end_break' => $endBreak,
            'duration' => $duration,
            'start_color_class' => $startColorClass,
            'end_color_class' => $endColorClass,
            'in_photo' => $inPhoto,
            'out_photo' => $outPhoto,
            'break_status_label' => $breakStatusLabel,
            'break_status_color' => $breakStatusColor,
            'detail_data' => $detailData,
        ];
    }
}
